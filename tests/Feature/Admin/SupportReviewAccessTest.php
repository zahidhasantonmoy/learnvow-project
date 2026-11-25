<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\ReviewResource\Pages\ListReviews;
use App\Filament\Resources\ReviewResource\Pages\ViewReview;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Review;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Role-audit Low-3 fix (option i): Support gets VIEW-ONLY access to
 * /admin/reviews — list + view — while approve/reject/hide stay
 * admin-only. The roles doc ("review reported books/reviews") already
 * allows Support to review reported reviews; the 403 contradicted it.
 */
class SupportReviewAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $support;

    private User $reader;

    private AdminService $service;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'author', 'admin', 'super-admin', 'support', 'print-partner'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create(['phone' => '01700000001']);
        AdminService::assignRole($this->admin, 'admin');

        $this->support = User::factory()->create();
        AdminService::assignRole($this->support, 'support');

        $this->reader = User::factory()->create();
        AdminService::assignRole($this->reader, 'reader');

        $this->service = app(AdminService::class);
    }

    private function makeReview(string $status = 'pending'): Review
    {
        $author = Author::create(['name' => 'Rev Author', 'slug' => 'rev-author-'.uniqid(), 'status' => 'approved']);
        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'rev-novel-'.uniqid()]);
        $book = Book::create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'title' => 'Review Book',
            'slug' => 'review-book-'.uniqid(),
            'description' => 'A book under review.',
            'language' => 'en',
            'price_bdt' => 96,
            'list_price_bdt' => 120,
            'status' => 'published',
        ]);

        return Review::create([
            'user_id' => $this->reader->id,
            'book_id' => $book->id,
            'rating' => 4,
            'title' => 'A thoughtful review',
            'body' => 'Worth reading.',
            'status' => $status,
        ]);
    }

    public function test_support_can_list_reviews(): void
    {
        $review = $this->makeReview();

        $this->actingAs($this->support)
            ->get('/admin/reviews')
            ->assertOk()
            ->assertSee('Review Book');
    }

    public function test_support_can_view_a_review(): void
    {
        $review = $this->makeReview();

        $this->actingAs($this->support)
            ->get("/admin/reviews/{$review->id}")
            ->assertOk()
            ->assertSee('A thoughtful review')
            ->assertSee('Worth reading.');
    }

    public function test_support_cannot_see_moderation_actions_on_the_list(): void
    {
        $review = $this->makeReview('pending');

        $this->actingAs($this->support);
        Livewire::test(ListReviews::class)
            ->assertTableActionHidden('approve', $review->id)
            ->assertTableActionHidden('reject', $review->id)
            ->assertTableActionHidden('hide', $review->id);
    }

    public function test_support_cannot_see_moderation_actions_on_the_view_page(): void
    {
        $review = $this->makeReview('pending');

        $this->actingAs($this->support);
        Livewire::test(ViewReview::class, ['record' => $review->id])
            ->assertActionHidden('approve')
            ->assertActionHidden('reject')
            ->assertActionHidden('hide');
    }

    public function test_support_cannot_moderate_a_review_via_the_service(): void
    {
        $review = $this->makeReview('pending');

        $this->expectException(AuthorizationException::class);

        $this->service->moderateReview($review, 'approved', $this->support);
    }

    public function test_admin_still_can_moderate_reviews(): void
    {
        $review = $this->makeReview('pending');

        $this->actingAs($this->admin);
        Livewire::test(ListReviews::class)
            ->assertTableActionVisible('approve', $review->id)
            ->callTableAction('approve', $review->id)
            ->assertHasNoErrors();

        $this->assertSame('approved', $review->fresh()->status);
    }

    public function test_reader_still_cannot_open_reviews(): void
    {
        $this->makeReview();

        $this->actingAs($this->reader)->get('/admin/reviews')->assertForbidden();
    }
}
