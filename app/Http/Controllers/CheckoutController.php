<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Services\BillingService;
use App\Services\BookService;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{
    use BuildsSeo;

    /** Book checkout page (route-model binding by slug). */
    public function book(string $locale, Book $book, BillingService $billing, BookService $books)
    {
        $user = auth()->user();

        // Author cannot buy their own book (roles doc edge case 4).
        $authorId = \DB::table('authors')->where('user_id', $user->id)->value('id');
        if ($authorId !== null && (int) $book->author_id === (int) $authorId) {
            return redirect()->route('books.show', ['locale' => app()->getLocale(), 'slug' => $book->slug])
                ->with('status', __('billing.author_cannot_buy_own'));
        }
        // AUD3-07 / X5: only a PURCHASE blocks re-buying. A Premium user
        // can read the book via Premium but does not OWN it — buying
        // gives permanent ownership that survives Premium ending.
        if ($books->ownsBook($user, $book)) {
            return redirect()->route('books.show', ['locale' => app()->getLocale(), 'slug' => $book->slug])
                ->with('status', __('billing.already_own_book'));
        }

        return view('checkout.index', [
            'book' => $book,
            'plan' => null,
            'type' => 'book',
            'priced' => $billing->priceOrder(['user' => $user, 'type' => 'book', 'book_id' => $book->id]),
            'bkashNumber' => $billing->setting('payment.bkash_number', ''),
            'nagadNumber' => $billing->setting('payment.nagad_number', ''),
            'seo' => $this->seo(__('billing.checkout_title'), null, ['noindex' => true]),
        ]);
    }

    /** Premium buy / renew checkout page. */
    public function premium(string $locale, BillingService $billing)
    {
        $user = auth()->user();
        $plan = $billing->premiumPlan();
        if ($plan === null) {
            abort(404);
        }

        return view('checkout.index', [
            'book' => null,
            'plan' => $plan,
            'type' => 'premium',
            'priced' => $billing->priceOrder(['user' => $user, 'type' => 'premium', 'plan_id' => $plan->id]),
            'bkashNumber' => $billing->setting('payment.bkash_number', ''),
            'nagadNumber' => $billing->setting('payment.nagad_number', ''),
            'seo' => $this->seo(__('billing.checkout_title'), null, ['noindex' => true]),
        ]);
    }

    /** International checkout (spec §3.1, §14.6): manual TrxID only — no gateway button. */
    public function international(string $locale, BillingService $billing)
    {
        $user = auth()->user();
        $plan = $billing->internationalPlan();
        if ($plan === null) {
            abort(404);
        }

        return view('checkout.index', [
            'book' => null,
            'plan' => $plan,
            'type' => 'premium',
            'priced' => $billing->priceOrder(['user' => $user, 'type' => 'premium', 'plan_id' => $plan->id]),
            'bkashNumber' => $billing->setting('payment.bkash_number', ''),
            'nagadNumber' => $billing->setting('payment.nagad_number', ''),
            'international' => true,
            'seo' => $this->seo(__('billing.intl_title'), null, ['noindex' => true]),
        ]);
    }

    /**
     * Create the order (and TrxID when a payment is due). The Livewire
     * CheckoutForm posts here; pricing is recomputed server-side.
     */
    public function store(string $locale, Request $request, BillingService $billing, BookService $books)
    {
        $data = $request->validate([
            'type' => 'required|in:book,premium,gift,wallet_topup',
            'book_id' => 'nullable|exists:books,id',
            'plan_id' => 'nullable|exists:subscription_plans,id',
            'coupon_code' => 'nullable|string|max:32',
            'wallet_use_bdt' => 'nullable|integer|min:0',
            'topup_amount_bdt' => 'nullable|integer|min:1',
            'method' => 'nullable|in:bkash,nagad',
            'trx_id' => 'nullable|string|max:32',
        ]);

        $user = auth()->user();
        $data['user'] = $user;

        if ($data['type'] === 'book' || $data['type'] === 'gift') {
            $book = Book::findOrFail($data['book_id']);
            $authorId = \DB::table('authors')->where('user_id', $user->id)->value('id');
            if ($authorId !== null && (int) $book->author_id === (int) $authorId) {
                return back()->with('status', __('billing.author_cannot_buy_own'));
            }
            // AUD3-07: same purchase-ownership guard as the checkout
            // page — a direct POST must not re-buy an owned book.
            // (Gift purchases are for someone else, so they are exempt.)
            if ($data['type'] === 'book' && $books->ownsBook($user, $book)) {
                return back()->with('status', __('billing.already_own_book'));
            }
        }

        $priced = $billing->priceOrder($data);

        // AUD3-02: validate a supplied TrxID BEFORE creating the order —
        // a bad TrxID used to leave an orphan pending_payment order.
        // (An empty TrxID is allowed: the order is created and the user
        // submits the TrxID on the fix-TrxID page.)
        if (! empty($data['trx_id']) && ! BillingService::isValidTrxId($data['trx_id'])) {
            return back()->withErrors(['trx_id' => __('billing.trxid_invalid')])->withInput();
        }

        $order = $billing->createOrder($user, $priced);

        if ($order->total_bdt === 0) {
            return redirect()->route('payment.success', ['locale' => app()->getLocale(), 'order' => $order->id]);
        }

        if (empty($data['trx_id'])) {
            // Order created; the user still has to submit the TrxID.
            return redirect()->route('payment.fix-trxid.edit', ['locale' => app()->getLocale(), 'order' => $order->id])
                ->with('status', __('billing.order_created_submit_trxid'));
        }

        try {
            $billing->submitTrxId($order, $data['method'] ?? 'bkash', $data['trx_id']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['trx_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('orders.show', ['locale' => app()->getLocale(), 'order' => $order->id])
            ->with('status', __('billing.trxid_submitted'));
    }
}
