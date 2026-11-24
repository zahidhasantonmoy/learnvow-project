<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\Preorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Pre-orders = reservation only, no money (§4.8, A8). */
class PreorderController extends Controller
{
    use BuildsSeo;

    public function index(Request $request, string $locale)
    {
        return view('library.preorders', [
            'preorders' => Preorder::where('user_id', $request->user()->id)
                ->with('book')->latest()->get(),
            'seo' => $this->seo(__('books.preorders_title'), null, ['noindex' => true]),
        ]);
    }

    public function store(Request $request, string $locale, Book $book)
    {
        if (! $this->isPreorderable($book)) {
            throw ValidationException::withMessages([
                'book' => __('books.preorder_not_available'),
            ]);
        }

        $existing = Preorder::where('user_id', $request->user()->id)
            ->where('book_id', $book->id)
            ->whereIn('status', Preorder::ACTIVE_STATUSES)
            ->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'book' => __('books.preorder_already_reserved'),
            ]);
        }

        Preorder::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            'status' => 'reserved',
        ]);

        return back()->with('status', __('books.preorder_reserved'));
    }

    public function destroy(Request $request, string $locale, Preorder $preorder)
    {
        Gate::authorize('delete', $preorder);
        $preorder->status = 'cancelled';
        $preorder->save();

        return back()->with('status', __('books.preorder_cancelled'));
    }

    /**
     * Pre-orderable when is_preorder is set and the book is in review
     * or its release date is in the future. The is_preorder columns
     * land with Builder C's §12.2 migration — until then, in_review
     * alone qualifies (guarded, never crashes).
     */
    public function isPreorderable(Book $book): bool
    {
        if (Schema::hasColumn('books', 'is_preorder') && ! $book->is_preorder) {
            return false;
        }

        if ($book->status === 'in_review') {
            return true;
        }

        if (Schema::hasColumn('books', 'preorder_release_at')
            && $book->preorder_release_at !== null
            && $book->preorder_release_at->isFuture()
        ) {
            return true;
        }

        return false;
    }
}
