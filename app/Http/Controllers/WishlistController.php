<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    use BuildsSeo;

    public function index(Request $request)
    {
        $items = Wishlist::where('user_id', $request->user()->id)
            ->with('book.author', 'book.category')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('wishlist.index', [
            'items' => $items,
            'seo' => $this->seo(__('books.wishlist_title'), null, ['noindex' => true]),
        ]);
    }

    public function toggle(Request $request, string $locale, string $slug)
    {
        $book = Book::published()->where('slug', $slug)->firstOrFail();
        $user = $request->user();

        $existing = Wishlist::where('user_id', $user->id)->where('book_id', $book->id)->first();

        if ($existing) {
            $existing->delete();
            $inWishlist = false;
        } else {
            Wishlist::create(['user_id' => $user->id, 'book_id' => $book->id]);
            $inWishlist = true;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['in_wishlist' => $inWishlist]);
        }

        return back()->with('status', $inWishlist
            ? __('books.added_to_wishlist')
            : __('books.removed_from_wishlist'));
    }
}
