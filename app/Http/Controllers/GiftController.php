<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Services\BillingService;
use Illuminate\Http\Request;

class GiftController extends Controller
{
    use BuildsSeo;

    /** Gift a book — checkout page for a gift order. */
    public function buy(string $locale, Book $book, BillingService $billing)
    {
        $user = auth()->user();
        $authorId = \DB::table('authors')->where('user_id', $user->id)->value('id');
        if ($authorId !== null && (int) $book->author_id === (int) $authorId) {
            return redirect()->route('books.show', ['locale' => app()->getLocale(), 'slug' => $book->slug])
                ->with('status', __('billing.author_cannot_buy_own'));
        }

        return view('gift.buy', [
            'book' => $book,
            'priced' => $billing->priceOrder(['user' => $user, 'type' => 'gift', 'book_id' => $book->id]),
            'bkashNumber' => $billing->setting('payment.bkash_number', ''),
            'nagadNumber' => $billing->setting('payment.nagad_number', ''),
            'seo' => $this->seo(__('billing.gift_title'), null, ['noindex' => true]),
        ]);
    }

    /** Redeem page — guests may view; claiming needs login (spec §8.2). */
    public function redeem(string $locale, Request $request)
    {
        return view('gift.redeem', [
            'code' => (string) $request->query('code', ''),
            'seo' => $this->seo(__('billing.gift_redeem_title'), null, ['noindex' => true]),
        ]);
    }

    public function claim(string $locale, Request $request, BillingService $billing)
    {
        $data = $request->validate(['code' => 'required|string|max:32']);

        try {
            $gift = $billing->redeemGift($data['code'], auth()->user());
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('books.show', ['locale' => app()->getLocale(), 'slug' => $gift->book->slug])
            ->with('status', __('billing.gift_claimed'));
    }
}
