<?php

namespace Tests\Feature\Author;

use App\Models\Coupon;
use App\Models\Review;
use App\Models\ReviewReply;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuthorService;
use Illuminate\Http\UploadedFile;

class ReviewReplyPromoSettingsTest extends AuthorTestCase
{
    protected \App\Models\Book $ownBook;

    protected function setUp(): void
    {
        parent::setUp();

        // The author's own published book (Prophet is Gibran's — use a
        // fresh book owned by the demo author).
        $this->ownBook = app(AuthorService::class)->publishBook(
            $this->author, $this->publishData(), $this->validEpub()
        );
        app(\App\Services\AdminService::class)->approveBook($this->ownBook);
        $this->ownBook = $this->ownBook->fresh();
    }

    public function test_one_reply_per_review_and_editable(): void
    {
        $review = Review::create([
            'user_id' => $this->reader->id, 'book_id' => $this->ownBook->id,
            'rating' => 5, 'title' => 'Great', 'body' => 'Loved it.', 'status' => 'approved',
        ]);

        $service = app(AuthorService::class);

        $reply = $service->replyToReview($this->author, $review, 'Thank you!');
        $this->assertSame('Thank you!', $reply->body);

        // A second reply edits the same row — still one reply.
        $service->replyToReview($this->author, $review, 'Thank you so much!');
        $this->assertSame(1, ReviewReply::where('review_id', $review->id)->count());
        $this->assertSame('Thank you so much!', ReviewReply::first()->body);
    }

    public function test_author_cannot_reply_to_another_authors_review(): void
    {
        $review = Review::create([
            'user_id' => $this->reader->id, 'book_id' => $this->prophet->id,
            'rating' => 4, 'title' => 'Good', 'body' => 'Good book.', 'status' => 'approved',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        app(AuthorService::class)->replyToReview($this->author, $review, 'Thanks!');
    }

    public function test_author_cannot_delete_a_reply(): void
    {
        $review = Review::create([
            'user_id' => $this->reader->id, 'book_id' => $this->ownBook->id,
            'rating' => 5, 'title' => 'Great', 'body' => 'Loved it.', 'status' => 'approved',
        ]);
        $reply = app(AuthorService::class)->replyToReview($this->author, $review, 'Thank you!');

        $this->assertFalse($this->authorUser->can('delete', $reply));
        $this->assertTrue($this->authorUser->can('update', $reply));
    }

    public function test_reviews_page_shows_only_approved_reviews_of_own_books(): void
    {
        Review::create([
            'user_id' => $this->reader->id, 'book_id' => $this->ownBook->id,
            'rating' => 5, 'title' => 'Great', 'body' => 'Approved review body.', 'status' => 'approved',
        ]);
        // reviews are unique per (user, book) — the pending one is from
        // a different reader.
        Review::create([
            'user_id' => User::factory()->create()->id, 'book_id' => $this->ownBook->id,
            'rating' => 1, 'title' => 'Bad', 'body' => 'Pending review body.', 'status' => 'pending',
        ]);

        $this->actingAuthor()
            ->get('/bn/author/reviews')
            ->assertOk()
            ->assertSee('Approved review body.')
            ->assertDontSee('Pending review body.');
    }

    public function test_promo_blocked_until_super_admin_sets_limits(): void
    {
        $service = app(AuthorService::class);
        $this->assertFalse($service->canCreatePromo());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/not set promo limits/');
        $service->createPromo($this->author, [
            'book_id' => $this->ownBook->id, 'percent' => 10, 'duration_days' => 7,
        ]);

        // The promos page says so, with no hardcoded limit anywhere.
        $this->actingAuthor()
            ->get('/bn/author/promos')
            ->assertOk()
            ->assertSee(__('author.promo_no_limits'));
    }

    public function test_promo_only_for_own_book_and_within_limits(): void
    {
        Setting::updateOrCreate(['key' => 'promo.max_percent'], ['value' => '20']);
        Setting::updateOrCreate(['key' => 'promo.max_duration_days'], ['value' => '14']);

        $service = app(AuthorService::class);
        $this->assertTrue($service->canCreatePromo());

        $coupon = $service->createPromo($this->author, [
            'book_id' => $this->ownBook->id, 'code' => 'AUTHOR10',
            'percent' => 10, 'duration_days' => 7,
        ]);
        $this->assertSame('AUTHOR10', $coupon->code);
        $this->assertSame($this->author->id, $coupon->author_id);
        $this->assertSame($this->ownBook->id, $coupon->book_id);
    }

    public function test_promo_over_owner_limit_is_rejected(): void
    {
        Setting::updateOrCreate(['key' => 'promo.max_percent'], ['value' => '20']);
        Setting::updateOrCreate(['key' => 'promo.max_duration_days'], ['value' => '14']);

        $this->expectException(\InvalidArgumentException::class);
        app(AuthorService::class)->createPromo($this->author, [
            'book_id' => $this->ownBook->id, 'code' => 'AUTHOR50',
            'percent' => 50, 'duration_days' => 7,
        ]);
    }

    public function test_promo_for_another_authors_book_is_rejected(): void
    {
        Setting::updateOrCreate(['key' => 'promo.max_percent'], ['value' => '20']);
        Setting::updateOrCreate(['key' => 'promo.max_duration_days'], ['value' => '14']);

        $this->expectException(\InvalidArgumentException::class);
        app(AuthorService::class)->createPromo($this->author, [
            'book_id' => $this->prophet->id, 'code' => 'AUTHOR11',
            'percent' => 10, 'duration_days' => 7,
        ]);
    }

    public function test_settings_page_shows_only_last_3_digits_of_bkash(): void
    {
        $this->author->update(['bkash_number' => '01712345678']);

        $this->actingAuthor()
            ->get('/bn/author/settings')
            ->assertOk()
            ->assertSee('··678')
            ->assertDontSee('01712345678');
    }

    public function test_bkash_change_validates_format(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(AuthorService::class)->updateAuthorSettings($this->author, ['bkash_number' => '123']);
    }

    public function test_audio_upload_sets_audiobook_flag(): void
    {
        $service = app(AuthorService::class);

        $this->assertFalse($this->ownBook->has_audiobook);

        $file = $service->addAudio(
            $this->author, $this->ownBook,
            UploadedFile::fake()->create('chapter1.mp3', 100, 'audio/mpeg')
        );

        $this->assertSame('audio', $file->type);
        $this->assertTrue($this->ownBook->fresh()->has_audiobook);

        // One audio file per book — a second upload replaces, not adds.
        $service->addAudio(
            $this->author, $this->ownBook,
            UploadedFile::fake()->create('chapter2.mp3', 100, 'audio/mpeg')
        );
        $this->assertSame(1, \App\Models\BookFile::where('book_id', $this->ownBook->id)->where('type', 'audio')->count());
    }

    public function test_book_without_audio_shows_coming_soon(): void
    {
        $this->assertFalse($this->ownBook->has_audiobook);

        $this->actingAuthor()
            ->get("/bn/author/books/{$this->ownBook->slug}/audio")
            ->assertOk()
            ->assertSee(__('author.audio_coming_soon'));
    }
}
