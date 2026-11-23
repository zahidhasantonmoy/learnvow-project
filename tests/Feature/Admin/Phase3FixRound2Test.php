<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Widgets\AdminOrdersChartWidget;
use App\Livewire\AuthorApplicationForm;
use App\Livewire\SupportTicketForm;
use App\Models\Author;
use App\Models\AuthorEarning;
use App\Models\Book;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\AuthorNotification;
use App\Services\AdminService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fix round 2 (re-QA 8/10): one test per fixed behaviour.
 * M4 (Super Admin guard form errors) + four minors.
 */
class Phase3FixRound2Test extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $admin;

    private User $reader;

    private User $support;

    private User $authorUser;

    private Author $author;

    private Book $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        foreach (['reader', 'author', 'admin', 'super-admin', 'support'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel']);

        $this->superAdmin = User::factory()->create(['phone' => '01700000000']);
        $this->superAdmin->assignRole('super-admin');
        $this->admin = User::factory()->create(['phone' => '01700000001']);
        $this->admin->assignRole('admin');
        $this->reader = User::factory()->create(['phone' => '01700000002']);
        $this->reader->assignRole('reader');
        $this->support = User::factory()->create(['phone' => '01700000009']);
        $this->support->assignRole('support');

        $this->authorUser = User::factory()->create(['phone' => '01700000003']);
        $this->authorUser->assignRole('reader');
        $this->authorUser->assignRole('author');
        $this->author = Author::create([
            'name' => 'Kahlil Gibran', 'slug' => 'kahlil-gibran',
            'user_id' => $this->authorUser->id, 'status' => 'approved',
            'bkash_number' => '01700000003',
        ]);

        $this->prophet = Book::create([
            'author_id' => $this->author->id, 'category_id' => $category->id,
            'title' => 'The Prophet', 'slug' => 'the-prophet', 'description' => 'Prophet.',
            'language' => 'en', 'price_bdt' => 96, 'list_price_bdt' => 120, 'status' => 'published',
        ]);

        foreach ([
            'pool.share_pct' => '80', 'pool.fee_rate_pct' => '2',
            'pool.count_min_minutes' => '10', 'pool.count_min_percent' => '10',
            'pool.min_payout_bdt' => '500', 'pool.payout_day' => '10',
            'pool.carry_bdt' => '0', 'subscription.price_bdt' => '99',
            'points.per_purchase' => '0',
        ] as $key => $value) {
            Setting::create(['key' => $key, 'value' => $value]);
        }
    }

    // ---- M4: Super Admin guards surface as form errors, never silent ----

    public function test_m4_granting_super_admin_to_another_user_throws_and_changes_nothing(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('admin.users_extra.only_one_super_admin'));

        try {
            AdminService::syncRolesForUser($this->reader, ['reader', 'super-admin'], $this->superAdmin);
        } finally {
            // The guard must throw instead of silently pretending success.
            $this->assertFalse(AdminService::userHasRole($this->reader->fresh(), 'super-admin'));
            $this->assertSame(1, AdminService::countSuperAdmins());
        }
    }

    public function test_m4_removing_sole_super_admin_role_throws_and_keeps_role(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage(__('admin.users_extra.sole_super_admin_keep_role'));

        try {
            AdminService::syncRolesForUser($this->superAdmin, ['admin'], $this->superAdmin);
        } finally {
            // No silent re-add: the role must still be there because the
            // sync was refused, not because it was quietly restored.
            $this->assertTrue(AdminService::userHasRole($this->superAdmin->fresh(), 'super-admin'));
            $this->assertSame(1, AdminService::countSuperAdmins());
        }
    }

    public function test_m4_filament_edit_save_shows_field_error_granting_super_admin(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(EditUser::class, ['record' => $this->reader->getRouteKey()])
            ->set('data.roles', ['reader', 'super-admin'])
            ->call('save')
            ->assertHasErrors(['data.roles'])
            ->assertSee(__('admin.users_extra.only_one_super_admin'));

        $this->assertFalse(AdminService::userHasRole($this->reader->fresh(), 'super-admin'));
        $this->assertSame(1, AdminService::countSuperAdmins());
    }

    public function test_m4_filament_edit_save_shows_field_error_removing_sole_super_admin(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(EditUser::class, ['record' => $this->superAdmin->getRouteKey()])
            ->set('data.roles', ['admin'])
            ->call('save')
            ->assertHasErrors(['data.roles'])
            ->assertSee(__('admin.users_extra.sole_super_admin_keep_role'));

        $this->assertTrue(AdminService::userHasRole($this->superAdmin->fresh(), 'super-admin'));
    }

    public function test_m4_filament_edit_save_still_allows_a_legal_role_change(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(EditUser::class, ['record' => $this->reader->getRouteKey()])
            ->set('data.roles', ['reader', 'support'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(AdminService::userHasRole($this->reader->fresh(), 'support'));
    }

    // ---- Minor: notification list shows sentences, not raw event keys ----

    public function test_notifications_render_human_sentences_in_bengali(): void
    {
        app()->setLocale('bn');

        $this->authorUser->notify(new AuthorNotification('book_approved', [
            'book_id' => $this->prophet->id, 'title' => 'The Prophet',
        ]));
        $this->authorUser->notify(new AuthorNotification('payout_released', [
            'payout_id' => 1, 'period' => '2026-11', 'total_bdt' => 636,
        ]));
        $this->authorUser->notify(new AuthorNotification('ticket_reply', [
            'ticket_id' => 1, 'ticket_no' => 'LV-T-2026-0001',
        ]));

        $this->actingAs($this->authorUser)->get('/bn/notifications')
            ->assertOk()
            ->assertSee('আপনার বই “The Prophet” অনুমোদিত হয়েছে।', false)
            ->assertSee('2026-11 মাসের ৳636 পেআউট ছাড়া হয়েছে।', false)
            ->assertSee('LV-T-2026-0001 টিকিটে নতুন উত্তর এসেছে।', false)
            ->assertDontSee('book_approved')
            ->assertDontSee('payout_released')
            ->assertDontSee('ticket_reply');
    }

    public function test_notifications_render_human_sentences_in_english(): void
    {
        app()->setLocale('en');

        $this->authorUser->notify(new AuthorNotification('book_approved', [
            'book_id' => $this->prophet->id, 'title' => 'The Prophet',
        ]));

        $this->actingAs($this->authorUser)->get('/en/notifications')
            ->assertOk()
            ->assertSee('Your book “The Prophet” was approved.', false)
            ->assertDontSee('book_approved');
    }

    public function test_notification_describe_falls_back_for_unknown_types(): void
    {
        $described = AuthorNotification::describe(['type' => 'something_new']);

        $this->assertSame(__('author.notifications.unknown'), $described['text']);
        $this->assertNull($described['url']);
    }

    // ---- Minor: Support dashboard hides the orders chart ----

    public function test_orders_chart_is_hidden_for_support_but_visible_for_admin(): void
    {
        $this->actingAs($this->support);
        $this->assertFalse(AdminOrdersChartWidget::canView());

        $this->actingAs($this->admin);
        $this->assertTrue(AdminOrdersChartWidget::canView());

        $this->actingAs($this->superAdmin);
        $this->assertTrue(AdminOrdersChartWidget::canView());
    }

    public function test_support_dashboard_shows_no_orders_chart(): void
    {
        // The chart widget is lazy-loaded (its heading only arrives on a
        // later Livewire request), so the dashboard HTML is asserted on
        // the widget component itself: present for Admin, absent for
        // Support.
        $this->actingAs($this->support)->get('/admin')
            ->assertOk()
            ->assertDontSee('AdminOrdersChartWidget');

        $this->actingAs($this->admin)->get('/admin')
            ->assertOk()
            ->assertSee('AdminOrdersChartWidget', false);
    }

    // ---- Minor: payouts:close writes the audit row, exactly once ----

    public function test_payouts_close_command_writes_period_closed_audit_row(): void
    {
        AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => $this->prophet->id,
            'period' => '2026-11', 'type' => 'sale', 'amount_bdt' => 600, 'share_pct' => 80,
        ]);

        $this->assertSame(0, Artisan::call('payouts:close', ['period' => '2026-11']));

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'payout.period_closed',
            'subject_type' => 'author_payouts',
        ]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'payout.period_closed')->count());
    }

    public function test_close_period_service_writes_exactly_one_audit_row(): void
    {
        AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => $this->prophet->id,
            'period' => '2026-11', 'type' => 'sale', 'amount_bdt' => 600, 'share_pct' => 80,
        ]);

        app(PayoutService::class)->closePeriod('2026-11');

        $this->assertSame(1, DB::table('audit_logs')->where('action', 'payout.period_closed')->count());
    }

    // ---- Minor: Bengali validation messages on BN forms ----

    public function test_author_apply_document_error_is_bengali_on_bn_page(): void
    {
        app()->setLocale('bn');
        $this->actingAs($this->reader);

        Livewire::test(AuthorApplicationForm::class)
            ->set('bkashNumber', '01700000002')
            ->call('submit')
            ->assertHasErrors(['document'])
            ->assertSee(__('author.apply_document_required'));
    }

    public function test_ticket_subject_error_is_bengali_on_bn_page(): void
    {
        app()->setLocale('bn');
        $this->actingAs($this->reader);

        Livewire::test(SupportTicketForm::class)
            ->set('subject', '')
            ->set('category', 'payment')
            ->set('body', 'I paid but the book is not unlocked yet.')
            ->call('submit')
            ->assertHasErrors(['subject'])
            ->assertSee(__('support.tickets.subject_required'));
    }

    public function test_ticket_subject_error_is_english_on_en_page(): void
    {
        app()->setLocale('en');
        $this->actingAs($this->reader);

        Livewire::test(SupportTicketForm::class)
            ->set('subject', '')
            ->set('category', 'payment')
            ->set('body', 'I paid but the book is not unlocked yet.')
            ->call('submit')
            ->assertHasErrors(['subject'])
            ->assertSee(__('support.tickets.subject_required'));
    }
}
