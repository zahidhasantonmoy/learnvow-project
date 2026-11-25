<?php

namespace Tests\Feature\Earnings;

use App\Livewire\PayoutRequest;
use App\Models\Author;
use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Models\Book;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 3 §2.4 + §11 — PayoutRequest Livewire component and the
 * Earning/Payout policies (own data only; approve/release = super-admin).
 */
class PayoutRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $authorUser;

    private User $otherAuthorUser;

    private User $superAdmin;

    private Author $author;

    private Author $otherAuthor;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'author', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel']);

        $this->authorUser = User::factory()->create(['phone' => '01700000003']);
        $this->authorUser->assignRole('author');
        $this->author = Author::create([
            'name' => 'Demo Author', 'slug' => 'demo-author',
            'user_id' => $this->authorUser->id, 'status' => 'approved',
        ]);

        $this->otherAuthorUser = User::factory()->create(['phone' => '01700000004']);
        $this->otherAuthorUser->assignRole('author');
        $this->otherAuthor = Author::create([
            'name' => 'Other Author', 'slug' => 'other-author',
            'user_id' => $this->otherAuthorUser->id, 'status' => 'approved',
        ]);

        Book::create([
            'author_id' => $this->author->id, 'category_id' => $category->id,
            'title' => 'Placeholder', 'slug' => 'placeholder', 'description' => 'x.',
            'language' => 'en', 'price_bdt' => 0, 'status' => 'published',
        ]);

        $this->superAdmin = User::factory()->create(['phone' => '01700000000']);
        $this->superAdmin->assignRole('super-admin');

        Setting::create(['key' => 'pool.payout_day', 'value' => '10']);
        Setting::create(['key' => 'pool.min_payout_bdt', 'value' => '500']);
    }

    public function test_payout_request_shows_balance_and_can_be_sent(): void
    {
        AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => null, 'period' => '2026-11',
            'type' => 'pool', 'amount_bdt' => 636, 'share_pct' => 80,
        ]);

        Livewire::actingAs($this->authorUser)
            ->test(PayoutRequest::class)
            ->assertSee('636')
            ->call('submit')
            ->assertSet('requested', true)
            ->assertSee(__('payouts.request_sent'));
    }

    /**
     * Low-1 (role audit 2026-11-25): the audit's fresh author (balance
     * ৳282) got "Payout request sent." twice. Below the ৳500 minimum
     * the request is now rejected with the minimum error, `requested`
     * stays false, and NO PayoutRequested notification is created.
     */
    public function test_payout_request_below_minimum_is_rejected_with_error_and_no_notification(): void
    {
        AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => null, 'period' => '2026-11',
            'type' => 'pool', 'amount_bdt' => 282, 'share_pct' => 80,
        ]);

        Livewire::actingAs($this->authorUser)
            ->test(PayoutRequest::class)
            ->assertSee('282')
            ->call('submit')
            ->assertSet('requested', false)
            ->assertSet('error', __('payouts.request_minimum', ['min' => '500', 'balance' => '282']))
            ->assertSee(__('payouts.request_minimum', ['min' => '500', 'balance' => '282']))
            ->assertDontSee(__('payouts.request_sent'));

        $this->assertSame(0, DB::table('notifications')
            ->where('type', 'App\\Notifications\\PayoutRequested')->count());
    }

    /** Boundary: exactly ৳500 is accepted and notifies the Super Admin. */
    public function test_payout_request_at_exactly_minimum_is_accepted_and_notifies(): void
    {
        AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => null, 'period' => '2026-11',
            'type' => 'pool', 'amount_bdt' => 500, 'share_pct' => 80,
        ]);

        Livewire::actingAs($this->authorUser)
            ->test(PayoutRequest::class)
            ->call('submit')
            ->assertSet('requested', true)
            ->assertSet('error', null)
            ->assertSee(__('payouts.request_sent'));

        $this->assertSame(1, DB::table('notifications')
            ->where('type', 'App\\Notifications\\PayoutRequested')->count());
    }

    /** The POST route entry point enforces the same minimum (no "sent" flash below ৳500). */
    public function test_payout_request_route_below_minimum_is_rejected(): void
    {
        AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => null, 'period' => '2026-11',
            'type' => 'pool', 'amount_bdt' => 282, 'share_pct' => 80,
        ]);

        $this->actingAs($this->authorUser)
            ->from('/en/author/earnings')
            ->post('/en/author/payouts/request')
            ->assertSessionHasErrors('payout');

        $this->assertSame(0, DB::table('notifications')
            ->where('type', 'App\\Notifications\\PayoutRequested')->count());
    }

    public function test_author_can_view_own_earnings_only(): void
    {
        $mine = AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => null, 'period' => '2026-11',
            'type' => 'pool', 'amount_bdt' => 100, 'share_pct' => 80,
        ]);
        $theirs = AuthorEarning::create([
            'author_id' => $this->otherAuthor->id, 'book_id' => null, 'period' => '2026-11',
            'type' => 'pool', 'amount_bdt' => 100, 'share_pct' => 80,
        ]);

        $this->assertTrue($this->authorUser->can('view', $mine));
        $this->assertFalse($this->authorUser->can('view', $theirs));
        $this->assertTrue($this->superAdmin->can('view', $theirs));
    }

    public function test_payout_policy_blocks_admin_from_approving(): void
    {
        $admin = User::factory()->create(['phone' => '01700000001']);
        $admin->assignRole('admin');

        $payout = AuthorPayout::create([
            'author_id' => $this->author->id, 'period' => '2026-11',
            'sales_bdt' => 1000, 'pool_bdt' => 0, 'total_bdt' => 1000,
            'method' => 'bkash', 'status' => 'pending',
        ]);

        $this->assertTrue($this->authorUser->can('view', $payout));
        $this->assertFalse($this->otherAuthorUser->can('view', $payout));
        $this->assertFalse($admin->can('approve', $payout));
        $this->assertFalse($admin->can('release', $payout));
        $this->assertTrue($this->superAdmin->can('approve', $payout));
        $this->assertTrue($this->superAdmin->can('release', $payout));
    }
}
