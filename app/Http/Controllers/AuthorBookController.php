<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Services\AuthorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AuthorBookController extends Controller
{
    use BuildsSeo;

    protected function author(AuthorService $authors)
    {
        $author = $authors->approvedAuthorFor(auth()->user());
        abort_unless($author !== null, 403);

        return $author;
    }

    /** Publish form (Livewire AuthorUpload). */
    public function create(string $locale, AuthorService $authors)
    {
        // Minor: never a bare 403 here. An approved author gets the form;
        // a pending applicant gets the friendly "waiting for approval"
        // message (no form); a Reader who never applied is sent to apply.
        $author = $authors->approvedAuthorFor(auth()->user());

        if ($author !== null) {
            return view('author.publish', [
                'author' => $author,
                'categories' => Category::active()->orderBy('sort_order')->get(),
                'seo' => $this->seo(__('author.publish_title'), null, ['noindex' => true]),
            ]);
        }

        if ($authors->activeApplicationFor(auth()->user()) !== null) {
            return view('author.publish', [
                'author' => null,
                'pendingApproval' => true,
                'categories' => Category::active()->orderBy('sort_order')->get(),
                'seo' => $this->seo(__('author.publish_title'), null, ['noindex' => true]),
            ]);
        }

        return redirect()->route('author.apply', ['locale' => $locale])
            ->with('status', __('author.apply_first'));
    }

    /** Non-Livewire fallback submit (the form is Livewire AuthorUpload). */
    public function store(string $locale, Request $request, AuthorService $authors)
    {
        $author = $this->author($authors);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'title_en' => 'nullable|string|max:255',
            'description' => 'required|string|max:10000',
            'description_en' => 'nullable|string|max:10000',
            'language' => 'required|in:bn,en',
            'category_id' => 'required|exists:categories,id',
            'price_bdt' => 'required|integer|min:0',
            'list_price_bdt' => 'nullable|integer|min:0',
            'isbn' => 'nullable|string|max:20',
            'pages' => 'nullable|integer|min:1',
            'sample_percent' => 'nullable|integer|min:0|max:100',
            'is_subscription_included' => 'nullable|boolean',
            'allow_pod' => 'nullable|boolean',
            'allow_licensed_pdf' => 'nullable|boolean',
            'print_price_bdt' => 'nullable|integer|min:0',
            'book_file' => 'required|file|mimes:epub,pdf|max:51200',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|dimensions:min_width=1600,min_height=2400|max:5120',
        ], [
            // M6: publish-form errors in the page locale, not Laravel English.
            'book_file.mimes' => __('author.publish_book_file_mimes'),
            'cover.dimensions' => __('author.publish_cover_dimensions'),
        ]);

        try {
            $book = $authors->publishBook(
                $author, $data,
                $request->file('book_file'),
                $request->file('cover')
            );
        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();

            if (str_contains($message, 'mimetype or META-INF/container.xml')) {
                $message = __('author.publish_invalid_epub');
            } elseif (str_contains($message, 'Cover must be at least')) {
                $message = __('author.publish_cover_too_small');
            }

            return back()->withErrors(['book_file' => $message])->withInput();
        }

        return redirect()->route('author.books', ['locale' => $locale])
            ->with('status', __('author.publish_success'));
    }

    /** My books (spec §1.4). */
    public function index(string $locale, AuthorService $authors)
    {
        $author = $this->author($authors);
        $books = $author->books()->with('category')->latest()->paginate(20);

        return view('author.books.index', [
            'author' => $author,
            'books' => $books,
            'seo' => $this->seo(__('author.books_title'), null, ['noindex' => true]),
        ]);
    }

    /** Edit book & pricing (Livewire AuthorBookEditor). */
    public function edit(string $locale, Book $book, AuthorService $authors)
    {
        Gate::authorize('manageBook', [Author::class, $book]);
        $author = $this->author($authors);

        return view('author.books.edit', [
            'author' => $author,
            'book' => $book,
            'seo' => $this->seo(__('author.edit_title'), null, ['noindex' => true]),
        ]);
    }

    public function update(string $locale, Book $book, Request $request, AuthorService $authors)
    {
        Gate::authorize('manageBook', [Author::class, $book]);
        $author = $this->author($authors);

        $data = $request->validate([
            'price_bdt' => 'required|integer|min:0',
            'list_price_bdt' => 'nullable|integer|min:0',
            'title_en' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:10000',
            'description_en' => 'nullable|string|max:10000',
        ]);

        $authors->updatePrice($author, $book, $data);

        return redirect()->route('author.books', ['locale' => $locale])
            ->with('status', __('author.price_success'));
    }

    public function pause(string $locale, Book $book, AuthorService $authors)
    {
        Gate::authorize('manageBook', [Author::class, $book]);
        $authors->pauseBook($this->author($authors), $book);

        return back()->with('status', __('author.pause_success'));
    }

    public function resume(string $locale, Book $book, AuthorService $authors)
    {
        Gate::authorize('manageBook', [Author::class, $book]);
        $authors->resumeBook($this->author($authors), $book);

        return back()->with('status', __('author.resume_success'));
    }

    public function appeal(string $locale, Book $book, Request $request, AuthorService $authors)
    {
        Gate::authorize('manageBook', [Author::class, $book]);

        $data = $request->validate(['note' => 'required|string|max:2000']);

        try {
            $authors->fileAppeal($this->author($authors), $book, $data['note']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['note' => $e->getMessage()]);
        }

        return back()->with('status', __('author.appeal_success'));
    }
}
