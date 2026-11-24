<?php

namespace App\Livewire;

use App\Models\Book;
use App\Services\AuthorService;
use InvalidArgumentException;
use Livewire\Component;

/** Edit book & pricing + pause/resume (§1.4, §11). */
class AuthorBookEditor extends Component
{
    public Book $book;
    public int $priceBdt = 0;
    public ?int $listPriceBdt = null;
    public string $titleEn = '';
    public string $description = '';
    public string $descriptionEn = '';
    public ?string $error = null;
    public ?string $notice = null;

    public function mount(Book $book): void
    {
        $this->book = $book;
        $this->priceBdt = (int) $book->price_bdt;
        $this->listPriceBdt = $book->list_price_bdt !== null ? (int) $book->list_price_bdt : null;
        $this->titleEn = $book->title_en ?? '';
        $this->description = $book->description;
        $this->descriptionEn = $book->description_en ?? '';
    }

    public function save(AuthorService $authors)
    {
        $this->error = null;
        $this->notice = null;
        $this->validate([
            // EB-09: sane upper bound, and the list price must never be
            // below the sale price (a "discount" that costs more).
            'priceBdt' => 'required|integer|min:0|max:100000',
            'listPriceBdt' => 'nullable|integer|min:0|max:100000|gte:priceBdt',
            'titleEn' => 'nullable|string|max:255',
            'description' => 'required|string|max:10000',
            'descriptionEn' => 'nullable|string|max:10000',
        ], [
            // Round 3: novalidate lets Livewire run, so the required
            // errors must be localized, not Laravel's English defaults.
            'priceBdt.required' => __('author.edit_price_required'),
            'priceBdt.max' => __('author.edit_price_max'),
            'listPriceBdt.gte' => __('author.edit_list_price_gte'),
            'description.required' => __('author.edit_description_required'),
        ]);

        $author = $authors->approvedAuthorFor(auth()->user());
        if ($author === null) {
            abort(403);
        }

        try {
            $this->book = $authors->updatePrice($author, $this->book, [
                'price_bdt' => $this->priceBdt,
                'list_price_bdt' => $this->listPriceBdt,
                'title_en' => $this->titleEn !== '' ? $this->titleEn : null,
                'description' => $this->description,
                'description_en' => $this->descriptionEn !== '' ? $this->descriptionEn : null,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->notice = __('author.price_success');
    }

    public function pause(AuthorService $authors)
    {
        $author = $authors->approvedAuthorFor(auth()->user());
        abort_unless($author !== null, 403);
        $this->book = $authors->pauseBook($author, $this->book);
        $this->notice = __('author.pause_success');
    }

    public function resume(AuthorService $authors)
    {
        $author = $authors->approvedAuthorFor(auth()->user());
        abort_unless($author !== null, 403);
        $this->book = $authors->resumeBook($author, $this->book);
        $this->notice = __('author.resume_success');
    }

    public function render()
    {
        return view('livewire.author-book-editor');
    }
}
