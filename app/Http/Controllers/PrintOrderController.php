<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\PrintOrder;
use App\Services\PrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/** Reader print orders (Phase 4 spec §2.1, §14.4). */
class PrintOrderController extends Controller
{
    use BuildsSeo;

    /** Order form (Livewire PrintOrderForm renders the price + fields). */
    public function create(string $locale, Book $book, PrintService $prints)
    {
        // UI-04: these failures used to `back()` — which landed the user
        // on whatever page they came from (clubs, wallet, support chat…).
        // Always land back on the book page with the message instead.
        $bookUrl = redirect()->route('books.show', ['locale' => $locale, 'slug' => $book->slug]);

        if (! $book->allow_pod || $book->status !== 'published' || $book->is_paused) {
            // The layout renders only session('status') — withErrors alone
            // is invisible to the user (QA M5 pattern, cf. LicensedPdfController).
            return $bookUrl->withErrors(['book' => __('print.not_available')])
                ->with('status', __('print.not_available'));
        }

        try {
            $priced = $prints->priceOrder($book, 1);
        } catch (InvalidArgumentException) {
            return $bookUrl->withErrors(['book' => __('print.price_not_set')])
                ->with('status', __('print.price_not_set'));
        }

        return view('print.order', [
            'book' => $book,
            'priced' => $priced,
            'seo' => $this->seo(__('print.order_title'), null, ['noindex' => true]),
        ]);
    }

    /** Non-Livewire fallback submit (same service, same rules). */
    public function store(string $locale, Request $request, Book $book, PrintService $prints)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
            'delivery_name' => 'required|string|max:255',
            'delivery_phone' => 'required|string|max:32',
            'delivery_address' => 'required|string|max:1000',
            'district' => 'nullable|string|max:100',
        ]);

        try {
            $order = $prints->createOrder($request->user(), $book, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['book' => $e->getMessage()])->withInput()
                ->with('status', $e->getMessage());
        }

        return redirect()->route('print-orders.show', ['locale' => $locale, 'printOrder' => $order->id])
            ->with('status', __('print.order_created'));
    }

    public function index(string $locale)
    {
        // AUD3-06: "my print orders" is a buyer page. A print partner
        // (who has no reader role) used to open it and see an empty
        // state; their orders live on the partner dashboard instead.
        abort_if(
            auth()->user()->hasRole('print-partner') && ! auth()->user()->hasRole('reader'),
            403
        );

        $orders = PrintOrder::where('user_id', auth()->id())
            ->with('book')->latest()->paginate(12);

        return view('print.orders', [
            'orders' => $orders,
            'seo' => $this->seo(__('print.orders_title'), null, ['noindex' => true]),
        ]);
    }

    public function show(string $locale, PrintOrder $printOrder)
    {
        Gate::authorize('view', $printOrder);

        return view('print.show', [
            'order' => $printOrder->load(['book', 'partner']),
            'seo' => $this->seo(__('print.orders_title'), null, ['noindex' => true]),
        ]);
    }

    /** Buyer submits the manual TrxID after Send Money (§2.1). */
    public function submitTrxId(string $locale, Request $request, PrintOrder $printOrder, PrintService $prints)
    {
        Gate::authorize('view', $printOrder);

        $data = $request->validate([
            'method' => 'required|in:bkash,nagad',
            'trx_id' => 'required|string|max:32',
        ]);

        try {
            $prints->submitTrxId($printOrder, $data['method'], $data['trx_id']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['trx_id' => $e->getMessage()])->withInput()
                ->with('status', $e->getMessage());
        }

        return back()->with('status', __('print.trxid_submitted'));
    }

    public function cancel(string $locale, PrintOrder $printOrder, PrintService $prints)
    {
        Gate::authorize('cancel', $printOrder);

        try {
            $prints->cancel($printOrder, auth()->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['order' => $e->getMessage()])
                ->with('status', $e->getMessage());
        }

        return back()->with('status', __('print.cancelled'));
    }
}
