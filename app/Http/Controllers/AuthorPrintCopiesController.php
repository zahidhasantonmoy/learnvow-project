<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Services\AuthorService;
use App\Services\PrintService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/** Author's own printed copies (Phase 4 spec §2.5, §14.4). */
class AuthorPrintCopiesController extends Controller
{
    use BuildsSeo;

    public function index(string $locale, AuthorService $authors)
    {
        Gate::authorize('viewStudio', \App\Models\Author::class);
        $author = $authors->approvedAuthorFor(auth()->user());
        abort_unless($author !== null, 403);

        $books = $author->publishedBooks()->orderBy('title')->get();

        return view('author.print-copies', [
            'author' => $author,
            'books' => $books,
            'seo' => $this->seo(__('print.author_copies_title'), null, ['noindex' => true]),
        ]);
    }

    public function store(string $locale, Request $request, AuthorService $authors, PrintService $prints)
    {
        Gate::authorize('viewStudio', \App\Models\Author::class);
        $author = $authors->approvedAuthorFor($request->user());
        abort_unless($author !== null, 403);

        $data = $request->validate([
            'book_id' => 'required|exists:books,id',
            'quantity' => 'required|integer|min:1|max:10',
            'delivery_name' => 'required|string|max:255',
            'delivery_phone' => 'required|string|max:32',
            'delivery_address' => 'required|string|max:1000',
            'district' => 'nullable|string|max:100',
        ]);

        $book = Book::findOrFail((int) $data['book_id']);

        // An author may only order their own book's copies.
        if ((int) $book->author_id !== (int) $author->id) {
            return back()->withErrors(['book' => __('print.not_available')])->withInput();
        }

        try {
            $order = $prints->createOrder($request->user(), $book, $data, true);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['book' => $e->getMessage()])->withInput();
        }

        return redirect()->route('print-orders.show', ['locale' => $locale, 'printOrder' => $order->id])
            ->with('status', __('print.order_created'));
    }
}
