<?php

namespace Tests\Feature\Auth;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Review;
use App\Models\User;
use App\Models\UserBook;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RolePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoUserSeeder::class);
    }

    private function user(string $phone): User
    {
        return User::where('phone', $phone)->firstOrFail();
    }

    private function makeBook(User $authorUser): Book
    {
        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel']);
        $author = Author::create(['user_id' => $authorUser->id, 'name' => 'Test Author', 'slug' => 'test-author', 'status' => 'approved']);

        return Book::create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'title' => 'Test Book',
            'slug' => 'test-book',
            'description' => 'A test book.',
            'language' => 'en',
            'price_bdt' => 100,
            'status' => 'published',
        ]);
    }

    // --- Seeders -------------------------------------------------------

    // Phase 4 (spec §17): 30 Phase 1–3 + 19 Phase 4 permissions = 49.
    public function test_six_roles_and_forty_nine_permissions_are_seeded(): void
    {
        $this->assertSame(6, \Spatie\Permission\Models\Role::count());
        $this->assertSame(49, \Spatie\Permission\Models\Permission::count());
        $this->assertNull(\Spatie\Permission\Models\Role::where('name', 'guest')->first());
    }

    public function test_exactly_one_super_admin_exists(): void
    {
        $this->assertSame(1, User::role('super-admin')->count());
        $this->assertSame('01700000000', User::role('super-admin')->first()->phone);
    }

    public function test_demo_author_has_reader_plus_author_roles(): void
    {
        $author = $this->user('01700000003');

        $this->assertTrue($author->hasRole('reader'));
        $this->assertTrue($author->hasRole('author'));
        $this->assertTrue($author->hasAllRoles(['reader', 'author']));
    }

    public function test_only_super_admin_can_manage_roles(): void
    {
        $this->assertTrue(Gate::forUser($this->user('01700000000'))->allows('roles.manage'));
        $this->assertFalse(Gate::forUser($this->user('01700000001'))->allows('roles.manage'));
        $this->assertFalse(Gate::forUser($this->user('01700000002'))->allows('roles.manage'));
        $this->assertFalse(Gate::forUser($this->user('01700000003'))->allows('roles.manage'));
    }

    public function test_panel_access_is_limited_to_staff_roles(): void
    {
        $panel = \Filament\Panel::make();

        $this->assertTrue($this->user('01700000000')->canAccessPanel($panel));
        $this->assertTrue($this->user('01700000001')->canAccessPanel($panel));
        $this->assertFalse($this->user('01700000002')->canAccessPanel($panel));
        $this->assertFalse($this->user('01700000003')->canAccessPanel($panel));

        $support = User::factory()->create(['phone' => '01899999999']);
        $support->assignRole('support');
        $this->assertTrue($support->canAccessPanel($panel));
    }

    // --- BookPolicy ----------------------------------------------------

    public function test_reader_cannot_approve_books_but_admin_can(): void
    {
        $book = $this->makeBook($this->user('01700000003'));

        $this->assertFalse($this->user('01700000002')->can('approve', $book));
        $this->assertFalse($this->user('01700000003')->can('approve', $book));
        $this->assertTrue($this->user('01700000001')->can('approve', $book));
        $this->assertTrue($this->user('01700000000')->can('approve', $book));
    }

    public function test_reader_cannot_reject_books(): void
    {
        $book = $this->makeBook($this->user('01700000003'));

        $this->assertFalse($this->user('01700000002')->can('reject', $book));
        $this->assertTrue($this->user('01700000001')->can('reject', $book));
    }

    public function test_only_the_books_author_can_edit_it(): void
    {
        $book = $this->makeBook($this->user('01700000003'));

        $this->assertTrue($this->user('01700000003')->can('update', $book));
        $this->assertFalse($this->user('01700000002')->can('update', $book));
        $this->assertFalse($this->user('01700000001')->can('update', $book));
        $this->assertTrue($this->user('01700000000')->can('update', $book));
    }

    // --- AuthorPolicy --------------------------------------------------

    public function test_only_admin_and_super_admin_can_approve_authors(): void
    {
        $author = Author::create(['name' => 'Pending Author', 'slug' => 'pending-author', 'status' => 'pending']);

        $this->assertFalse($this->user('01700000002')->can('approve', $author));
        $this->assertTrue($this->user('01700000001')->can('approve', $author));
        $this->assertTrue($this->user('01700000000')->can('approve', $author));
    }

    // --- UserPolicy ----------------------------------------------------

    public function test_only_super_admin_can_suspend_or_ban_users(): void
    {
        $reader = $this->user('01700000002');

        $this->assertFalse($this->user('01700000001')->can('suspend', $reader));
        $this->assertFalse($this->user('01700000001')->can('ban', $reader));
        $this->assertTrue($this->user('01700000000')->can('suspend', $reader));
        $this->assertTrue($this->user('01700000000')->can('ban', $reader));
    }

    public function test_super_admin_account_cannot_be_suspended_banned_or_deleted(): void
    {
        $owner = $this->user('01700000000');

        $this->assertFalse($owner->can('suspend', $owner));
        $this->assertFalse($owner->can('ban', $owner));
        $this->assertFalse($owner->can('delete', $owner));
    }

    public function test_admin_can_manage_users_but_not_delete_super_admin(): void
    {
        $reader = $this->user('01700000002');

        $this->assertTrue($this->user('01700000001')->can('manage', $reader));
        $this->assertFalse($this->user('01700000002')->can('manage', $reader));
        $this->assertFalse($this->user('01700000001')->can('delete', $reader));
        $this->assertTrue($this->user('01700000000')->can('delete', $reader));
    }

    // --- ReviewPolicy --------------------------------------------------

    public function test_only_admin_super_admin_and_support_can_moderate_reviews(): void
    {
        $reader = $this->user('01700000002');
        $book = $this->makeBook($this->user('01700000003'));
        $review = Review::create([
            'user_id' => $reader->id, 'book_id' => $book->id,
            'rating' => 5, 'body' => 'Great book.', 'status' => 'pending',
        ]);

        $this->assertFalse($reader->can('moderate', $review));
        $this->assertTrue($this->user('01700000001')->can('moderate', $review));
        $this->assertTrue($this->user('01700000000')->can('moderate', $review));

        $support = User::factory()->create(['phone' => '01899999999']);
        $support->assignRole('support');
        $this->assertTrue($support->can('moderate', $review));
    }

    public function test_review_can_only_be_written_by_a_reader_with_entitlement(): void
    {
        $reader = $this->user('01700000002');
        $book = $this->makeBook($this->user('01700000003'));

        $this->assertFalse($reader->can('create', [Review::class, $book]));

        UserBook::create(['user_id' => $reader->id, 'book_id' => $book->id, 'source' => 'free']);

        $this->assertTrue($reader->can('create', [Review::class, $book]));
    }
}
