<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\BookResource\Pages\ListBooks;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Builder C — the Filament approve action itself changes the DB status
 * (spec §4.4 adminBooks), not just the service underneath it.
 */
class AdminApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'author', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        AdminService::assignRole($this->admin, 'admin');
    }

    public function test_filament_approve_action_publishes_an_in_review_book(): void
    {
        $author = Author::create([
            'name' => 'Test Author', 'slug' => 'flow-author', 'status' => 'approved',
        ]);
        $category = Category::create([
            'name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'flow-novel',
        ]);
        $book = Book::create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'title' => 'Flow Book',
            'slug' => 'flow-book',
            'description' => 'A flow test book.',
            'language' => 'en',
            'price_bdt' => 96,
            'list_price_bdt' => 120,
            'status' => 'in_review',
        ]);

        $this->actingAs($this->admin);

        Livewire::test(ListBooks::class)
            ->callTableAction('approve', $book->getKey());

        $this->assertSame('published', $book->fresh()->status);
    }
}
