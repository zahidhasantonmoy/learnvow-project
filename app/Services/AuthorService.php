<?php

namespace App\Services;

use App\Models\Author;
use App\Models\AuthorApplication;
use App\Models\Book;
use App\Models\BookAppeal;
use App\Models\BookFile;
use App\Models\Coupon;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\User;
use App\Notifications\AuthorNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

/**
 * Author Studio service (Phase 3 — spec §1, §12).
 *
 * All Author Studio business logic lives here, never in controllers
 * (spec §0.1). Money is int (৳) only; prices are always recomputed
 * server-side and never trusted from the client.
 *
 * DEVIATION (local dev): BookFileService::presignedPutUrl() throws on the
 * local disk ("Direct uploads need the R2 disk") — there is no presigned
 * PUT without R2. Until production, publish/audio/cover uploads go
 * through PHP to the BookFileService disk (Storage abstraction, never a
 * hardcoded disk name). The presigned-PUT path is used automatically
 * once the disk is R2; see storeBookFile().
 */
class AuthorService
{
    public function __construct(protected BookFileService $files) {}

    // ------------------------------------------------------------------
    // Application (§1.1)
    // ------------------------------------------------------------------

    /** Bangladeshi bKash numbers are 11 digits starting with 01. */
    public static function isValidBkash(string $number): bool
    {
        return (bool) preg_match('/^01[3-9]\d{8}$/', preg_replace('/[\s\-]/', '', $number));
    }

    /** The approved Author row for this user, or null. */
    public function authorFor(User $user): ?Author
    {
        return Author::where('user_id', $user->id)->first();
    }

    public function approvedAuthorFor(User $user): ?Author
    {
        return Author::where('user_id', $user->id)->where('status', 'approved')->first();
    }

    /** The user's currently active application, if any (§7.1 note). */
    public function activeApplicationFor(User $user): ?AuthorApplication
    {
        return AuthorApplication::where('user_id', $user->id)
            ->whereIn('status', AuthorApplication::ACTIVE_STATUSES)
            ->latest()
            ->first();
    }

    /**
     * Submit a become-author application. One active application per
     * account; the bKash number is mandatory (payouts go there); the
     * ID/contract document is mandatory (spec A7) and private.
     */
    public function submitApplication(User $user, array $data, ?UploadedFile $document = null): AuthorApplication
    {
        $bkash = preg_replace('/[\s\-]/', '', (string) ($data['bkash_number'] ?? ''));

        if (! self::isValidBkash($bkash)) {
            throw new InvalidArgumentException('Invalid bKash number.');
        }

        if ($document === null) {
            throw new InvalidArgumentException('An ID / contract document is required.');
        }

        if ($this->approvedAuthorFor($user) !== null) {
            throw new InvalidArgumentException('User is already an approved author.');
        }

        if ($this->activeApplicationFor($user) !== null) {
            throw new InvalidArgumentException('An application is already under review.');
        }

        return DB::transaction(function () use ($user, $data, $bkash, $document) {
            $author = $this->authorFor($user);

            if ($author === null) {
                $author = Author::create([
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'pen_name' => $data['pen_name'] ?? null,
                    'slug' => $this->uniqueAuthorSlug($user->name),
                    'bio' => $data['bio'] ?? null,
                    'bio_en' => $data['bio_en'] ?? null,
                    'bkash_number' => $bkash,
                    'status' => 'pending',
                ]);
            } else {
                $author->update([
                    'pen_name' => $data['pen_name'] ?? $author->pen_name,
                    'bio' => $data['bio'] ?? $author->bio,
                    'bio_en' => $data['bio_en'] ?? $author->bio_en,
                    'bkash_number' => $bkash,
                    'status' => 'pending',
                ]);
            }

            $path = Storage::disk('local')->putFile('author-documents', $document);

            return AuthorApplication::create([
                'user_id' => $user->id,
                'author_id' => $author->id,
                'pen_name' => $data['pen_name'] ?? null,
                'bio' => $data['bio'] ?? null,
                'bio_en' => $data['bio_en'] ?? null,
                'bkash_number' => $bkash,
                'id_document_path' => $path,
                'status' => AuthorApplication::STATUS_SUBMITTED,
            ]);
        });
    }

    /**
     * Admin review of an application: delegates the author-state change
     * to AdminService::approveAuthor/rejectAuthor (never re-implemented
     * here) and records the decision on the application row.
     */
    public function reviewApplication(AuthorApplication $application, User $actor, bool $approve, ?string $note = null): AuthorApplication
    {
        $admin = app(AdminService::class);
        $author = $application->author ?? $this->authorFor($application->user);

        if ($author === null) {
            throw new InvalidArgumentException('No author row linked to this application.');
        }

        if ($approve) {
            $admin->approveAuthor($author, $actor);
            $application->status = AuthorApplication::STATUS_APPROVED;
        } else {
            $admin->rejectAuthor($author, $note, $actor);
            $application->status = AuthorApplication::STATUS_REJECTED;
        }

        $application->reviewed_by = $actor->id;
        $application->review_note = $note;
        $application->reviewed_at = now();
        $application->save();

        $this->notify($application->user, $approve ? 'application_approved' : 'application_rejected', [
            'application_id' => $application->id,
            'note' => $note,
        ]);

        return $application->fresh();
    }

    // ------------------------------------------------------------------
    // Publish (§1.3)
    // ------------------------------------------------------------------

    /** EPUB validation: real ZIP with mimetype + META-INF/container.xml. */
    public function validateEpub(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new InvalidArgumentException('The EPUB file is not a valid ZIP archive.');
        }

        try {
            // Plain names: FL_NODIR would strip the META-INF/ directory
            // and the lookup would fail on a valid EPUB.
            if ($zip->locateName('mimetype') === false
                || $zip->locateName('META-INF/container.xml') === false) {
                throw new InvalidArgumentException('The EPUB is missing mimetype or META-INF/container.xml.');
            }
        } finally {
            $zip->close();
        }
    }

    /** Cover validation: image at least 1600×2400 (spec §1.3, A6). */
    public function validateCover(string $path): void
    {
        $size = @getimagesize($path);

        if ($size === false) {
            throw new InvalidArgumentException('The cover is not a valid image.');
        }

        [$width, $height] = $size;
        $minW = $this->settingInt('upload.min_cover_width', 1600);
        $minH = $this->settingInt('upload.min_cover_height', 2400);

        if ($width < $minW || $height < $minH) {
            throw new InvalidArgumentException(
                "Cover must be at least {$minW}×{$minH} px (got {$width}×{$height})."
            );
        }
    }

    /**
     * Publish a book. The book always lands in `in_review` — never
     * `published` directly (§1.3). The 80% share is stated on the form
     * before the author commits; the share itself lives on the Author row.
     */
    public function publishBook(Author $author, array $data, ?UploadedFile $bookFile = null, ?UploadedFile $cover = null): Book
    {
        if ($author->status !== 'approved') {
            throw new InvalidArgumentException('Only an approved author can publish.');
        }

        if ($bookFile === null) {
            throw new InvalidArgumentException('A book file (EPUB or PDF) is required.');
        }

        // Extension decides the kind: real uploads are MIME-validated by
        // the controller/Livewire `mimes:` rule before they reach here.
        $isPdf = strtolower($bookFile->getClientOriginalExtension()) === 'pdf';

        $maxEpubMb = $this->settingInt('upload.max_epub_mb', 50);
        if ($bookFile->getSize() > $maxEpubMb * 1024 * 1024) {
            throw new InvalidArgumentException("Book file must be at most {$maxEpubMb} MB.");
        }

        if (! $isPdf) {
            $this->validateEpub($bookFile->getRealPath());
        }

        if ($cover !== null) {
            $this->validateCover($cover->getRealPath());
            $maxCoverMb = $this->settingInt('upload.max_cover_mb', 5);
            if ($cover->getSize() > $maxCoverMb * 1024 * 1024) {
                throw new InvalidArgumentException("Cover must be at most {$maxCoverMb} MB.");
            }
        }

        $price = (int) ($data['price_bdt'] ?? 0);
        $listPrice = isset($data['list_price_bdt']) && $data['list_price_bdt'] !== ''
            ? (int) $data['list_price_bdt'] : null;

        if ($price < 0 || ($listPrice !== null && $listPrice < 0)) {
            throw new InvalidArgumentException('Prices must be zero or more.');
        }

        if ($listPrice !== null && $listPrice > 0 && $listPrice < $price) {
            throw new InvalidArgumentException('List price cannot be below the sale price.');
        }

        return DB::transaction(function () use ($author, $data, $bookFile, $cover, $price, $listPrice) {
            $slug = $this->uniqueBookSlug((string) ($data['title'] ?? ''));

            $book = Book::create([
                'author_id' => $author->id,
                'category_id' => (int) $data['category_id'],
                'title' => $data['title'],
                'title_en' => $data['title_en'] ?? null,
                'slug' => $slug,
                'description' => $data['description'],
                'description_en' => $data['description_en'] ?? null,
                'language' => in_array($data['language'] ?? null, ['bn', 'en'], true) ? $data['language'] : 'bn',
                'price_bdt' => $price,
                'list_price_bdt' => $listPrice,
                'isbn' => $data['isbn'] ?? null,
                'pages' => isset($data['pages']) && $data['pages'] !== '' ? (int) $data['pages'] : null,
                'is_free' => $price === 0,
                'is_subscription_included' => (bool) ($data['is_subscription_included'] ?? true),
                'sample_percent' => min(100, max(0, (int) ($data['sample_percent'] ?? 10))),
                'status' => 'in_review',
                'allow_pod' => (bool) ($data['allow_pod'] ?? true),
                'allow_licensed_pdf' => (bool) ($data['allow_licensed_pdf'] ?? false),
                'print_price_bdt' => isset($data['print_price_bdt']) && $data['print_price_bdt'] !== ''
                    ? (int) $data['print_price_bdt'] : null,
            ]);

            $this->storeBookFile($book, $bookFile, 'epub');

            if ($cover !== null) {
                $file = $this->storeBookFile($book, $cover, 'cover');
                $book->cover_image_path = $file->path;
                $book->save();
            }

            return $book->fresh();
        });
    }

    /**
     * Store an uploaded book file on the BookFileService disk (local now,
     * R2 at production). Files never go through PHP on R2 — there the
     * caller uses presignedPutUrl(); locally that method throws, so the
     * file is stored server-side here instead (see class docblock).
     */
    public function storeBookFile(Book $book, UploadedFile $file, string $type): BookFile
    {
        $disk = $this->files->disk();
        $ext = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = $type.'/'.$book->slug.'-'.Str::random(8).'.'.$ext;

        Storage::disk($disk)->putFileAs(dirname($path), $file, basename($path));

        return BookFile::updateOrCreate(
            ['book_id' => $book->id, 'type' => $type],
            [
                'disk' => $disk,
                'path' => $path,
                'size_bytes' => $file->getSize(),
                'mime' => $file->getMimeType() ?? 'application/octet-stream',
            ]
        );
    }

    /** Add an audiobook file to a book (one audio file per book, §1.5). */
    public function addAudio(Author $author, Book $book, UploadedFile $audio): BookFile
    {
        $this->assertOwnBook($author, $book);

        $maxAudioMb = $this->settingInt('upload.max_audio_mb', 500);
        if ($audio->getSize() > $maxAudioMb * 1024 * 1024) {
            throw new InvalidArgumentException("Audio must be at most {$maxAudioMb} MB.");
        }

        if (! str_starts_with($audio->getMimeType() ?? '', 'audio/')
            && ! in_array(strtolower($audio->getClientOriginalExtension()), ['mp3', 'm4a', 'wav', 'ogg', 'aac', 'flac'], true)) {
            throw new InvalidArgumentException('The audio file must be an audio file.');
        }

        $file = $this->storeBookFile($book, $audio, 'audio');

        if (! $book->has_audiobook) {
            $book->has_audiobook = true;
            $book->save();
        }

        return $file;
    }

    // ------------------------------------------------------------------
    // Price / pause / appeal (§1.4, §1.3)
    // ------------------------------------------------------------------

    /**
     * Change a book's price. Previous orders are untouched — order_items
     * hold their own price snapshot (Phase 2). The change is audit-logged
     * (audit_logs when Builder C's table exists, else the app log).
     * No admin approval is needed (spec A5).
     */
    public function updatePrice(Author $author, Book $book, array $data): Book
    {
        $this->assertOwnBook($author, $book);

        $oldPrice = (int) $book->price_bdt;
        $oldList = $book->list_price_bdt !== null ? (int) $book->list_price_bdt : null;

        $price = (int) ($data['price_bdt'] ?? $oldPrice);
        $listPrice = array_key_exists('list_price_bdt', $data) && $data['list_price_bdt'] !== ''
            ? (int) $data['list_price_bdt'] : $oldList;

        if ($price < 0 || ($listPrice !== null && $listPrice < 0)) {
            throw new InvalidArgumentException('Prices must be zero or more.');
        }

        $book->price_bdt = $price;
        $book->list_price_bdt = $listPrice;
        $book->is_free = $price === 0;

        if (isset($data['description'])) {
            $book->description = $data['description'];
        }
        if (array_key_exists('description_en', $data)) {
            $book->description_en = $data['description_en'];
        }
        if (isset($data['title_en'])) {
            $book->title_en = $data['title_en'];
        }

        $book->save();

        $this->audit('book.price_changed', 'books', $book->id, $author->user_id, [
            'old_price_bdt' => $oldPrice,
            'new_price_bdt' => $price,
            'old_list_price_bdt' => $oldList,
            'new_list_price_bdt' => $listPrice,
        ]);

        return $book->fresh();
    }

    /** Pause sales: the book leaves the catalogue/search; old buyers keep access (canRead unchanged). */
    public function pauseBook(Author $author, Book $book): Book
    {
        $this->assertOwnBook($author, $book);
        $book->is_paused = true;
        $book->save();

        return $book->fresh();
    }

    public function resumeBook(Author $author, Book $book): Book
    {
        $this->assertOwnBook($author, $book);
        $book->is_paused = false;
        $book->save();

        return $book->fresh();
    }

    /**
     * Appeal a rejected book: an appeal row is filed and the book goes
     * back to `in_review` for admin re-check (target: within 2 days, A10).
     */
    public function fileAppeal(Author $author, Book $book, string $note): BookAppeal
    {
        $this->assertOwnBook($author, $book);

        if ($book->status !== 'rejected') {
            throw new InvalidArgumentException('Only a rejected book can be appealed.');
        }

        if (trim($note) === '') {
            throw new InvalidArgumentException('An appeal note is required.');
        }

        return DB::transaction(function () use ($author, $book, $note) {
            $appeal = BookAppeal::create([
                'book_id' => $book->id,
                'author_id' => $author->id,
                'note' => trim($note),
                'status' => 'open',
            ]);

            $book->status = 'in_review';
            $book->save();

            return $appeal;
        });
    }

    // ------------------------------------------------------------------
    // Print settings (§1.6) — permissions only, no orders (Phase 4)
    // ------------------------------------------------------------------

    public function updatePrintSettings(Author $author, Book $book, array $data): Book
    {
        $this->assertOwnBook($author, $book);

        $book->allow_pod = (bool) ($data['allow_pod'] ?? $book->allow_pod);
        $book->allow_licensed_pdf = (bool) ($data['allow_licensed_pdf'] ?? $book->allow_licensed_pdf);
        $book->print_price_bdt = isset($data['print_price_bdt']) && $data['print_price_bdt'] !== ''
            ? (int) $data['print_price_bdt'] : null;
        $book->save();

        return $book->fresh();
    }

    // ------------------------------------------------------------------
    // Author settings (§1.7)
    // ------------------------------------------------------------------

    public function updateAuthorSettings(Author $author, array $data): Author
    {
        if (isset($data['bkash_number']) && $data['bkash_number'] !== '') {
            $bkash = preg_replace('/[\s\-]/', '', (string) $data['bkash_number']);
            if (! self::isValidBkash($bkash)) {
                throw new InvalidArgumentException('Invalid bKash number.');
            }
            $author->bkash_number = $bkash;
        }

        if (isset($data['pen_name'])) {
            $author->pen_name = $data['pen_name'] !== '' ? $data['pen_name'] : null;
        }
        if (isset($data['bio'])) {
            $author->bio = $data['bio'];
        }
        if (array_key_exists('bio_en', $data)) {
            $author->bio_en = $data['bio_en'];
        }
        if (isset($data['payout_method']) && in_array($data['payout_method'], ['bkash', 'bank'], true)) {
            $author->payout_method = $data['payout_method'];
        }
        if (array_key_exists('bank_name', $data)) {
            $author->bank_name = $data['bank_name'] !== '' ? $data['bank_name'] : null;
        }
        if (isset($data['bank_account_number']) && $data['bank_account_number'] !== '') {
            $author->bank_account_number = $data['bank_account_number'];
        }

        $author->save();

        return $author->fresh();
    }

    /** Show only the last 3 digits of a bKash/bank number, never the full number. */
    public static function maskedNumber(?string $number): string
    {
        if ($number === null || $number === '') {
            return '—';
        }

        $digits = preg_replace('/\D/', '', $number);

        return '··'.substr($digits, -3);
    }

    // ------------------------------------------------------------------
    // Reviews & replies (§1.8)
    // ------------------------------------------------------------------

    /** The author's own books' approved reviews only (pending hidden). */
    public function reviewsFor(Author $author)
    {
        $bookIds = $author->books()->pluck('id');

        return Review::whereIn('book_id', $bookIds)
            ->where('status', 'approved')
            ->with(['book', 'user'])
            ->latest()
            ->get();
    }

    /** One public reply per review; editable, never deletable by the author. */
    public function replyToReview(Author $author, Review $review, string $body): ReviewReply
    {
        $this->assertOwnBook($author, $review->book);

        if (trim($body) === '') {
            throw new InvalidArgumentException('A reply body is required.');
        }

        $existing = ReviewReply::where('review_id', $review->id)->first();

        if ($existing !== null) {
            $existing->body = trim($body);
            $existing->save();

            return $existing->fresh();
        }

        return ReviewReply::create([
            'review_id' => $review->id,
            'author_id' => $author->id,
            'user_id' => $author->user_id,
            'body' => trim($body),
        ]);
    }

    // ------------------------------------------------------------------
    // Promos (§1.9) — Phase 4 §10 (decision 4, K1): promo guardrails are
    // fully manual. An UNSET limit means NO limit (never "blocked");
    // a set limit (1–100) is enforced; discount is never above 100%.
    // ------------------------------------------------------------------

    /** Promo limits come from settings only; null = no limit (Phase 4 §10). */
    public function promoLimits(): array
    {
        return [
            'max_percent' => $this->settingIntOrNull('promo.max_percent'),
            'max_duration_days' => $this->settingIntOrNull('promo.max_duration_days'),
        ];
    }

    /**
     * Phase 4 §10.2: limits no longer gate promo creation — an approved
     * author with a book can always create a promo. The controller
     * already aborts unless an approved author exists; this method only
     * records that the limits themselves never block.
     */
    public function canCreatePromo(): bool
    {
        return true;
    }

    /** Author coupon for their own book only, inside the owner's limits (if any). */
    public function createPromo(Author $author, array $data): Coupon
    {
        $limits = $this->promoLimits();

        $book = Book::findOrFail((int) ($data['book_id'] ?? 0));
        $this->assertOwnBook($author, $book);

        // Hard cap 100% (100% = free is allowed); a set limit caps lower.
        $cap = $limits['max_percent'] !== null ? min((int) $limits['max_percent'], 100) : 100;
        $percent = (int) ($data['percent'] ?? 0);
        if ($percent < 1 || $percent > $cap) {
            throw new InvalidArgumentException("Discount must be between 1% and {$cap}%.");
        }

        $startsAt = now();
        $endsAt = isset($data['ends_at']) ? now()->parse($data['ends_at']) : $startsAt->copy()->addDays((int) ($data['duration_days'] ?? 1));
        $durationDays = (int) $startsAt->diffInDays($endsAt);

        if ($durationDays < 1) {
            throw new InvalidArgumentException('Promo duration must be at least 1 day.');
        }

        if ($limits['max_duration_days'] !== null && $durationDays > $limits['max_duration_days']) {
            throw new InvalidArgumentException("Promo duration must be within {$limits['max_duration_days']} days.");
        }

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $code = 'LV'.strtoupper(Str::random(6));
        }

        if (Coupon::where('code', $code)->exists()) {
            throw new InvalidArgumentException('That coupon code already exists.');
        }

        return Coupon::create([
            'code' => $code,
            'percent' => $percent,
            'applies_to' => 'book',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'max_uses' => isset($data['max_uses']) ? (int) $data['max_uses'] : null,
            'max_uses_per_user' => 1,
            'used_count' => 0,
            'is_active' => true,
            'author_id' => $author->id,
            'book_id' => $book->id,
        ]);
    }

    // ------------------------------------------------------------------
    // Dashboard & analytics (§1.2) — live from DB, never hardcoded
    // ------------------------------------------------------------------

    /**
     * Dashboard figures. Reads author_earnings when Builder B's table
     * exists; otherwise returns zeroes (the frozen seed figures appear
     * once the seeder lands — they are never hardcoded here).
     */
    public function dashboardData(Author $author): array
    {
        $sales = 0;
        $pool = 0;
        $recentSales = collect();

        if (Schema::hasTable('author_earnings')) {
            $rows = DB::table('author_earnings')->where('author_id', $author->id)->get();
            $sales = (int) $rows->where('type', 'sale')->sum('amount_bdt')
                + (int) $rows->where('type', 'adjustment')->sum('amount_bdt');
            $pool = (int) $rows->where('type', 'pool')->sum('amount_bdt');
        }

        if (Schema::hasTable('orders') && Schema::hasTable('order_items')) {
            $bookIds = $author->books()->pluck('id');
            $recentSales = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->join('users', 'users.id', '=', 'orders.user_id')
                ->whereIn('order_items.book_id', $bookIds)
                ->where('orders.status', 'paid')
                ->orderByDesc('orders.created_at')
                ->limit(10)
                ->get(['order_items.title_snapshot as title', 'order_items.quantity', 'order_items.unit_price_bdt', 'orders.created_at', 'users.country']);
        }

        return [
            'total_bdt' => $sales + $pool,
            'sales_bdt' => $sales,
            'pool_bdt' => $pool,
            'next_payout' => $this->nextPayoutDate(),
            'recent_sales' => $recentSales,
            'share_pct' => (int) $author->revenue_share_pct,
        ];
    }

    /** Next payout day (10th of the month by default, setting pool.payout_day). */
    public function nextPayoutDate(): string
    {
        $day = $this->settingInt('pool.payout_day', 10);
        $candidate = now()->copy()->day(min($day, 28));

        if ($candidate->isPast()) {
            $candidate->addMonth();
        }

        return $candidate->format('Y-m-d');
    }

    /** Reading analytics for the author's own books only. */
    public function authorAnalytics(Author $author): array
    {
        $bookIds = $author->books()->pluck('id');
        $minutesByBook = [];
        $totalMinutes = 0;

        if (Schema::hasTable('reading_minutes')) {
            $rows = DB::table('reading_minutes')
                ->whereIn('book_id', $bookIds)
                ->where('flagged', false)
                ->selectRaw('book_id, SUM(minutes) as minutes')
                ->groupBy('book_id')
                ->get();

            foreach ($rows as $row) {
                $minutesByBook[(int) $row->book_id] = (int) $row->minutes;
                $totalMinutes += (int) $row->minutes;
            }
        }

        $books = $author->books()->with('category')->get()->map(function (Book $book) use ($minutesByBook) {
            return [
                'book' => $book,
                'minutes' => $minutesByBook[$book->id] ?? 0,
                'sales_count' => (int) $book->sales_count,
            ];
        });

        return [
            'books' => $books,
            'total_minutes' => $totalMinutes,
        ];
    }

    /**
     * Books a customer may buy: published, not paused, not hidden.
     * NOTE for the coordinator: BookService::list()/search()/show()
     * (Phase 1, coordinator-owned) still use scopePublished() alone —
     * they need `is_paused = false` + `hidden_at IS NULL` added there
     * before a paused book actually leaves the public catalogue.
     */
    public function saleableBooksQuery()
    {
        return Book::published()->where('is_paused', false)->whereNull('hidden_at');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public function assertOwnBook(Author $author, Book $book): void
    {
        if ((int) $book->author_id !== (int) $author->id) {
            throw new InvalidArgumentException('That book does not belong to this author.');
        }
    }

    protected function uniqueAuthorSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'author';
        $slug = $base;
        $i = 2;

        while (Author::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    protected function uniqueBookSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'book';
        $slug = $base;
        $i = 2;

        while (Book::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function setting(?string $key, ?string $default = null): ?string
    {
        if ($key === null || ! Schema::hasTable('settings')) {
            return $default;
        }

        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }

    public function settingInt(string $key, int $default = 0): int
    {
        $value = $this->setting($key);

        return $value === null || $value === '' ? $default : (int) $value;
    }

    /** Null when the setting is absent/empty (promo limits: unset = no limit, Phase 4 §10). */
    public function settingIntOrNull(string $key): ?int
    {
        $value = $this->setting($key);

        return $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * Audit a change. audit_logs is Builder C's table — until it lands,
     * the event is written to the app log instead of being lost.
     */
    protected function audit(string $action, ?string $subjectType, ?int $subjectId, ?int $actorId, array $details = []): void
    {
        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')->insert([
                'actor_id' => $actorId,
                'actor_role' => 'author',
                'action' => $action,
                'subject_type' => $subjectType,
                'subject_id' => $subjectId,
                'details' => json_encode($details),
                'created_at' => now(),
            ]);

            return;
        }

        Log::info('Author audit: '.$action, array_merge([
            'subject_type' => $subjectType, 'subject_id' => $subjectId, 'actor_id' => $actorId,
        ], $details));
    }

    /** In-app notification (database channel only, spec §6/A13). */
    protected function notify(?User $user, string $type, array $data = []): void
    {
        if ($user === null || ! Schema::hasTable('notifications')) {
            return;
        }

        $user->notify(new AuthorNotification($type, $data));
    }
}
