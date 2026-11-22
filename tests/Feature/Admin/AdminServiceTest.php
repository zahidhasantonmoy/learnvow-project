<?php

namespace Tests\Feature\Admin;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Review;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Builder C — AdminService approval rules (spec §6, §8).
 * Roles are assigned through AdminService (pivot), so these tests do not
 * depend on Builder A having added the HasRoles trait to User yet.
 */
class AdminServiceTest extends TestCase
{
    use RefreshDatabase;

    private AdminService $service;

    private User $admin;

    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AdminService::class);

        foreach (['reader', 'author', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        AdminService::assignRole($this->admin, 'admin');

        $this->reader = User::factory()->create();
        AdminService::assignRole($this->reader, 'reader');
    }

    private function makeBook(string $status = 'in_review'): Book
    {
        $author = Author::create([
            'name' => 'Test Author', 'slug' => 'test-author-'.$status.rand(1, 99999), 'status' => 'approved',
        ]);
        $category = Category::create([
            'name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel-'.$status.rand(1, 99999),
        ]);

        return Book::create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'title' => 'Test Book',
            'slug' => 'test-book-'.$status.rand(1, 99999),
            'description' => 'A test book.',
            'language' => 'en',
            'price_bdt' => 96,
            'list_price_bdt' => 120,
            'status' => $status,
        ]);
    }

    public function test_admin_can_approve_a_book_in_review(): void
    {
        $book = $this->makeBook('in_review');

        $this->service->approveBook($book, $this->admin);

        $this->assertSame('published', $book->fresh()->status);
        $this->assertNotNull($book->fresh()->published_at);
    }

    public function test_admin_can_reject_a_book(): void
    {
        $book = $this->makeBook('in_review');

        $this->service->rejectBook($book, 'Not ready', $this->admin);

        $this->assertSame('rejected', $book->fresh()->status);
    }

    public function test_approving_an_already_published_book_throws(): void
    {
        $book = $this->makeBook('published');

        $this->expectException(InvalidArgumentException::class);
        $this->service->approveBook($book, $this->admin);
    }

    public function test_rejecting_a_rejected_book_throws(): void
    {
        $book = $this->makeBook('rejected');

        $this->expectException(InvalidArgumentException::class);
        $this->service->rejectBook($book, null, $this->admin);
    }

    public function test_reader_cannot_approve_a_book(): void
    {
        $book = $this->makeBook('in_review');

        $this->expectException(AuthorizationException::class);
        $this->service->approveBook($book, $this->reader);
    }

    public function test_admin_can_approve_an_author_and_grant_the_author_role(): void
    {
        $authorUser = User::factory()->create();
        AdminService::assignRole($authorUser, 'reader');

        $author = Author::create([
            'user_id' => $authorUser->id, 'name' => 'New Author',
            'slug' => 'new-author', 'status' => 'pending',
        ]);

        $this->service->approveAuthor($author, $this->admin);

        $this->assertSame('approved', $author->fresh()->status);
        $this->assertContains('author', AdminService::userRoles($authorUser->fresh()));
    }

    public function test_admin_can_reject_an_author(): void
    {
        $author = Author::create([
            'name' => 'New Author', 'slug' => 'new-author-2', 'status' => 'pending',
        ]);

        $this->service->rejectAuthor($author, 'Incomplete', $this->admin);

        $this->assertSame('rejected', $author->fresh()->status);
    }

    public function test_reader_cannot_approve_an_author(): void
    {
        $author = Author::create([
            'name' => 'New Author', 'slug' => 'new-author-3', 'status' => 'pending',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->approveAuthor($author, $this->reader);
    }

    public function test_review_moderation_approve_reject_hide(): void
    {
        $book = $this->makeBook('published');
        $review = Review::create([
            'user_id' => $this->reader->id, 'book_id' => $book->id,
            'rating' => 5, 'body' => 'Great book.', 'status' => 'pending',
        ]);

        $this->service->moderateReview($review, 'approved', $this->admin);
        $this->assertSame('approved', $review->fresh()->status);

        $this->service->moderateReview($review, 'hidden', $this->admin);
        $this->assertSame('hidden', $review->fresh()->status);

        $this->service->moderateReview($review, 'rejected', $this->admin);
        $this->assertSame('rejected', $review->fresh()->status);
    }

    public function test_invalid_review_transition_throws(): void
    {
        $book = $this->makeBook('published');
        $review = Review::create([
            'user_id' => $this->reader->id, 'book_id' => $book->id,
            'rating' => 5, 'body' => 'Great book.', 'status' => 'approved',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service->moderateReview($review, 'pending', $this->admin);
    }

    public function test_reader_cannot_moderate_a_review(): void
    {
        $book = $this->makeBook('published');
        $review = Review::create([
            'user_id' => $this->reader->id, 'book_id' => $book->id,
            'rating' => 5, 'body' => 'Great book.', 'status' => 'pending',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->moderateReview($review, 'approved', $this->reader);
    }

    public function test_admin_cannot_manage_roles_but_super_admin_can(): void
    {
        $this->assertFalse(AdminService::canManageRoles($this->admin));
        $this->assertFalse(AdminService::canApprovePayouts($this->admin));

        $super = User::factory()->create();
        AdminService::assignRole($super, 'super-admin');

        $this->assertTrue(AdminService::canManageRoles($super));
        $this->assertTrue(AdminService::canAccessAdminPanel($super));
    }

    public function test_admin_role_sync_never_grants_or_removes_privileged_roles(): void
    {
        $target = User::factory()->create();
        AdminService::assignRole($target, 'reader');

        // An Admin "granting" admin + super-admin must not actually grant them.
        AdminService::syncRolesForUser($target, ['reader', 'admin', 'super-admin'], $this->admin);

        $this->assertSame(['reader'], AdminService::userRoles($target->fresh()));
    }
}
