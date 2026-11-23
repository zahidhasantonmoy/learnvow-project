<?php

namespace App\Livewire;

use App\Models\Category;
use App\Services\AuthorService;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Publish form (§1.3, §11) — metadata + EPUB/PDF + cover.
 * Local dev uploads go through PHP to the BookFileService disk
 * (presigned PUT needs R2; see AuthorService).
 */
class AuthorUpload extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $titleEn = '';
    public string $description = '';
    public string $descriptionEn = '';
    public string $language = 'bn';
    public ?int $categoryId = null;
    public int $priceBdt = 0;
    public ?int $listPriceBdt = null;
    public string $isbn = '';
    public ?int $pages = null;
    public int $samplePercent = 10;
    public bool $isSubscriptionIncluded = true;
    public bool $allowPod = true;
    public bool $allowLicensedPdf = false;
    public ?int $printPriceBdt = null;
    public $bookFile = null;
    public $cover = null;
    public ?string $error = null;

    public function submit(AuthorService $authors)
    {
        $this->error = null;
        // M6: publish-form validation errors render in the page locale
        // (bn/en lang keys), never in Laravel's English defaults.
        $this->validate([
            'title' => 'required|string|max:255',
            'titleEn' => 'nullable|string|max:255',
            'description' => 'required|string|max:10000',
            'descriptionEn' => 'nullable|string|max:10000',
            'language' => 'required|in:bn,en',
            'categoryId' => 'required|exists:categories,id',
            'priceBdt' => 'required|integer|min:0',
            'listPriceBdt' => 'nullable|integer|min:0',
            'isbn' => 'nullable|string|max:20',
            'pages' => 'nullable|integer|min:1',
            'samplePercent' => 'nullable|integer|min:0|max:100',
            'printPriceBdt' => 'nullable|integer|min:0',
            'bookFile' => 'required|file|mimes:epub,pdf|max:51200',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|dimensions:min_width=1600,min_height=2400|max:5120',
        ], [
            'title.required' => __('author.publish_title_required'),
            'description.required' => __('author.publish_description_required'),
            'language.required' => __('author.publish_language_required'),
            'categoryId.required' => __('author.publish_category_required'),
            'priceBdt.required' => __('author.publish_price_required'),
            'bookFile.required' => __('author.publish_file_required'),
            'bookFile.mimes' => __('author.publish_book_file_mimes'),
            'cover.dimensions' => __('author.publish_cover_dimensions'),
        ]);

        $author = $authors->approvedAuthorFor(auth()->user());
        if ($author === null) {
            $this->error = __('author.apply_waiting_approval');

            return;
        }

        try {
            $authors->publishBook($author, [
                'title' => $this->title,
                'title_en' => $this->titleEn !== '' ? $this->titleEn : null,
                'description' => $this->description,
                'description_en' => $this->descriptionEn !== '' ? $this->descriptionEn : null,
                'language' => $this->language,
                'category_id' => $this->categoryId,
                'price_bdt' => $this->priceBdt,
                'list_price_bdt' => $this->listPriceBdt,
                'isbn' => $this->isbn !== '' ? $this->isbn : null,
                'pages' => $this->pages,
                'sample_percent' => $this->samplePercent,
                'is_subscription_included' => $this->isSubscriptionIncluded,
                'allow_pod' => $this->allowPod,
                'allow_licensed_pdf' => $this->allowLicensedPdf,
                'print_price_bdt' => $this->printPriceBdt,
            ], $this->bookFile, $this->cover);
        } catch (InvalidArgumentException $e) {
            // M6: service errors are English strings — map the known ones
            // to the page locale instead of showing English on BN pages.
            $this->error = $this->localizePublishError($e->getMessage());

            return;
        }

        return redirect()->route('author.books', ['locale' => app()->getLocale()])
            ->with('status', __('author.publish_success'));
    }

    /** Map AuthorService's English publish errors to lang keys. */
    protected function localizePublishError(string $message): string
    {
        if (str_contains($message, 'mimetype or META-INF/container.xml')) {
            return __('author.publish_invalid_epub');
        }

        if (str_contains($message, 'Cover must be at least')) {
            return __('author.publish_cover_too_small');
        }

        return $message;
    }

    public function render(AuthorService $authors)
    {
        return view('livewire.author-upload', [
            'categories' => Category::active()->orderBy('sort_order')->get(),
            'sharePct' => (int) ($authors->approvedAuthorFor(auth()->user())?->revenue_share_pct ?? 80),
        ]);
    }
}
