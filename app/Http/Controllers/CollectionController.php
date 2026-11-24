<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Reader shelves (§4.2). */
class CollectionController extends Controller
{
    use BuildsSeo;

    public function index(Request $request, string $locale)
    {
        $user = $request->user();

        return view('library.collections.index', [
            'collections' => Collection::where('user_id', $user->id)
                ->withCount('items')->orderBy('sort_order')->orderBy('name')->get(),
            'seo' => $this->seo(__('books.collections_title'), null, ['noindex' => true]),
        ]);
    }

    public function store(Request $request, string $locale)
    {
        $user = $request->user();

        if (Collection::where('user_id', $user->id)->count() >= Collection::MAX_PER_USER) {
            throw ValidationException::withMessages([
                'name' => __('books.collections_limit_reached', ['max' => Collection::MAX_PER_USER]),
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('collections')->where('user_id', $user->id)],
            'name_en' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        Collection::create(array_merge($data, ['user_id' => $user->id]));

        return back()->with('status', __('books.collections_created'));
    }

    public function show(Request $request, string $locale, Collection $collection)
    {
        Gate::authorize('view', $collection);

        return view('library.collections.show', [
            'collection' => $collection->load(['items.book.author', 'items.book.category']),
            'seo' => $this->seo($collection->name, null, ['noindex' => true]),
        ]);
    }

    public function update(Request $request, string $locale, Collection $collection)
    {
        Gate::authorize('update', $collection);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('collections')->where('user_id', $request->user()->id)->ignore($collection->id)],
            'name_en' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $collection->update($data);

        return back()->with('status', __('books.collections_updated'));
    }

    public function destroy(Request $request, string $locale, Collection $collection)
    {
        Gate::authorize('delete', $collection);
        $collection->delete(); // items only — the books themselves stay.

        return redirect()->route('collections.index', ['locale' => app()->getLocale()])
            ->with('status', __('books.collections_deleted'));
    }

    public function addBook(Request $request, string $locale, Collection $collection, Book $book)
    {
        Gate::authorize('update', $collection);

        if ($collection->items()->count() >= Collection::MAX_BOOKS) {
            throw ValidationException::withMessages([
                'book' => __('books.collections_books_limit_reached', ['max' => Collection::MAX_BOOKS]),
            ]);
        }

        $collection->items()->firstOrCreate(
            ['book_id' => $book->id],
            ['sort_order' => (int) ($collection->items()->max('sort_order') ?? 0) + 1, 'added_at' => now()]
        );

        return back()->with('status', __('books.collections_book_added'));
    }

    public function removeBook(Request $request, string $locale, Collection $collection, Book $book)
    {
        Gate::authorize('update', $collection);
        $collection->items()->where('book_id', $book->id)->delete();

        return back()->with('status', __('books.collections_book_removed'));
    }
}
