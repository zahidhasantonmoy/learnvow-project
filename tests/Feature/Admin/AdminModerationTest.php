<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\AuditLog as AuditLogPage;
use App\Filament\Pages\PoolRules;
use App\Filament\Pages\Settings;
use App\Filament\Resources\CouponResource\Pages\CreateCoupon;
use App\Filament\Resources\PayoutResource;
use App\Models\AuditLog;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\Report;
use App\Models\Review;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\TakedownRequest;
use App\Models\User;
use App\Services\AdminService;
use Database\Seeders\AuditLogSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Builder C — Phase 3 moderation + Super Admin gates (spec §3.2, §4, §5).
 * Suspend/ban is Super Admin only; exactly one Super Admin; the audit
 * log is append-only; the §8.3 seed rows are verbatim.
 */
class AdminModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

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

        $this->superAdmin = User::factory()->create(['phone' => '01700000000']);
        AdminService::assignRole($this->superAdmin, 'super-admin');

        $this->support = User::factory()->create();
        AdminService::assignRole($this->support, 'support');

        $this->reader = User::factory()->create();
        AdminService::assignRole($this->reader, 'reader');

        $this->service = app(AdminService::class);
    }

    private function makeBook(string $title = 'Mod Book', string $status = 'published'): Book
    {
        $author = Author::create(['name' => 'Mod Author', 'slug' => 'mod-author-'.uniqid(), 'status' => 'approved']);
        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'mod-novel-'.uniqid()]);

        return Book::create([
            'author_id' => $author->id,
            'category_id' => $category->id,
            'title' => $title,
            'slug' => 'mod-book-'.uniqid(),
            'description' => 'A moderation test book.',
            'language' => 'en',
            'price_bdt' => 96,
            'list_price_bdt' => 120,
            'status' => $status,
        ]);
    }

    // ---- suspend / ban gates (§3.2, §5.4) ----

    public function test_admin_cannot_suspend_or_ban(): void
    {
        $target = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->service->suspendUser($target, $this->admin);
    }

    public function test_admin_cannot_ban(): void
    {
        $target = User::factory()->create();

        $this->expectException(AuthorizationException::class);
        $this->service->banUser($target, $this->admin);

        $this->assertSame('active', $target->fresh()->status);
    }

    public function test_super_admin_can_suspend_ban_and_restore_with_audit(): void
    {
        $target = User::factory()->create();

        $this->service->suspendUser($target, $this->superAdmin, 'Spam');
        $this->assertSame('suspended', $target->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.suspended', 'subject_id' => $target->id]);

        $this->service->restoreUser($target, $this->superAdmin);
        $this->assertSame('active', $target->fresh()->status);

        $this->service->banUser($target, $this->superAdmin, 'Fraud');
        $this->assertSame('banned', $target->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.banned', 'subject_id' => $target->id]);
    }

    public function test_ban_cancels_subscription_without_refund(): void
    {
        $target = User::factory()->create();
        $plan = SubscriptionPlan::create([
            'slug' => 'premium', 'name' => 'Premium', 'price_bdt' => 99,
            'duration_days' => 30, 'trial_days' => 7, 'is_active' => true,
        ]);
        $subscription = Subscription::create([
            'user_id' => $target->id, 'plan_id' => $plan->id, 'status' => 'active',
            'starts_at' => now(), 'ends_at' => now()->addMonth(),
        ]);

        $this->service->banUser($target, $this->superAdmin);

        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertSame('Account banned', $subscription->fresh()->cancel_reason);
    }

    public function test_super_admin_account_cannot_be_suspended_or_banned(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->suspendUser($this->superAdmin, $this->superAdmin);
    }

    // ---- exactly one Super Admin (§4) ----

    public function test_second_super_admin_creation_is_refused(): void
    {
        $other = User::factory()->create();

        // Fix round 1 (M4): the guard surfaces as a friendly validation
        // error on the Filament form, not a raw 500.
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        AdminService::assignRole($other, 'super-admin');
    }

    public function test_sole_super_admin_role_removal_is_refused(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        AdminService::removeRole($this->superAdmin, 'super-admin');
    }

    public function test_author_role_is_not_hand_assignable(): void
    {
        $this->assertNotContains('author', AdminService::assignableRolesFor($this->superAdmin));
        $this->assertNotContains('author', AdminService::assignableRolesFor($this->admin));
    }

    // ---- reports (§5.2) ----

    public function test_support_can_hide_reported_book_but_cannot_decide_report(): void
    {
        $book = $this->makeBook();
        $report = Report::create([
            'reporter_id' => $this->reader->id, 'reportable_type' => 'books',
            'reportable_id' => $book->id, 'reason' => 'spam',
        ]);

        $this->service->hideBook($book, $this->support, 'Reported as spam');
        $this->assertNotNull($book->fresh()->hidden_at);

        $this->expectException(AuthorizationException::class);
        $this->service->decideReport($report, 'dismiss', $this->support);
    }

    public function test_admin_dismisses_report_and_book_is_visible_again_with_audit(): void
    {
        $book = $this->makeBook();
        $this->service->hideBook($book, $this->support);

        $report = Report::create([
            'reporter_id' => $this->reader->id, 'reportable_type' => 'books',
            'reportable_id' => $book->id, 'reason' => 'spam',
        ]);

        $this->service->decideReport($report, 'dismiss', $this->admin, 'False alarm');

        $this->assertSame('dismissed', $report->fresh()->status);
        $this->assertNull($book->fresh()->hidden_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.decided', 'subject_id' => $report->id]);
    }

    public function test_admin_action_on_report_takes_book_down(): void
    {
        $book = $this->makeBook();
        $report = Report::create([
            'reporter_id' => $this->reader->id, 'reportable_type' => 'books',
            'reportable_id' => $book->id, 'reason' => 'copyright',
        ]);

        $this->service->decideReport($report, 'action', $this->admin, 'Confirmed');

        $this->assertSame('action_taken', $report->fresh()->status);
        $this->assertSame('taken_down', $book->fresh()->status);
    }

    public function test_admin_action_on_review_report_rejects_review(): void
    {
        $book = $this->makeBook();
        $review = Review::create([
            'user_id' => $this->reader->id, 'book_id' => $book->id,
            'rating' => 1, 'title' => 'Bad', 'body' => 'Spam review', 'status' => 'approved',
        ]);
        $report = Report::create([
            'reporter_id' => $this->reader->id, 'reportable_type' => 'reviews',
            'reportable_id' => $review->id, 'reason' => 'abuse',
        ]);

        $this->service->decideReport($report, 'action', $this->admin);

        $this->assertSame('rejected', $review->fresh()->status);
    }

    // ---- takedowns (§5.3, A9) ----

    public function test_takedown_flow_48h_deadline_and_7_day_extension(): void
    {
        $book = $this->makeBook();
        $claim = TakedownRequest::create([
            'book_id' => $book->id, 'claimant_name' => 'Claimant',
            'claimant_email' => 'claim@example.com', 'reason' => 'My book',
        ]);

        // 48h first-response target is set on creation.
        $this->assertNotNull($claim->deadline_at);
        $this->assertEqualsWithDelta(48 * 3600, $claim->created_at->diffInSeconds($claim->deadline_at), 5);

        $originalDeadline = $claim->fresh()->deadline_at;
        $this->service->decideTakedown($claim, 'extend_proof', $this->admin, 'Need more time');
        $this->assertSame('awaiting_proof', $claim->fresh()->status);
        $this->assertEqualsWithDelta(
            7 * 86400,
            $originalDeadline->diffInSeconds($claim->fresh()->deadline_at),
            5
        );

        $this->service->decideTakedown($claim, 'take_down', $this->admin, 'Under review');
        $this->assertSame('action_taken', $claim->fresh()->status);
        $this->assertSame('taken_down', $book->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'takedown.decided', 'subject_id' => $claim->id]);
    }

    public function test_takedown_reject_and_close_paths(): void
    {
        $book = $this->makeBook();

        $rejected = TakedownRequest::create([
            'book_id' => $book->id, 'claimant_name' => 'C', 'claimant_email' => 'c@example.com', 'reason' => 'X',
        ]);
        $this->service->decideTakedown($rejected, 'reject', $this->admin, 'No proof');
        $this->assertSame('rejected', $rejected->fresh()->status);

        $closed = TakedownRequest::create([
            'book_id' => $book->id, 'claimant_name' => 'C', 'claimant_email' => 'c@example.com', 'reason' => 'Y',
        ]);
        $this->service->decideTakedown($closed, 'close', $this->admin, 'Commissioned work verified');
        $this->assertSame('closed', $closed->fresh()->status);
    }

    // ---- audit log append-only (§4.5, §0.8) ----

    public function test_audit_log_is_append_only(): void
    {
        $log = AdminService::writeAudit($this->admin, 'test.action', 'books', 1, ['message' => 'hi']);
        $this->assertNotNull($log);

        $this->assertFalse($log->update(['action' => 'tampered']));
        $this->assertSame('test.action', $log->fresh()->action);

        $this->assertFalse($log->delete());
        $this->assertDatabaseHas('audit_logs', ['id' => $log->id]);

        // Even Super Admin cannot update/delete via policy.
        $this->assertFalse($this->superAdmin->can('update', $log));
        $this->assertFalse($this->superAdmin->can('delete', $log));
        $this->assertTrue($this->superAdmin->can('view', $log));
        $this->assertFalse($this->admin->can('view', $log));
    }

    // ---- audit seed rows (§8.3) ----

    public function test_audit_seed_rows_are_exact(): void
    {
        $book = $this->makeBook('পদ্মা নদীর মাঝি');
        Order::create([
            'order_no' => 'LV-2026-1042', 'user_id' => $this->reader->id, 'type' => 'premium',
            'status' => 'paid', 'subtotal_bdt' => 99, 'total_bdt' => 99, 'refund_status' => 'none',
        ]);

        (new AuditLogSeeder)->run();

        $this->assertSame(5, AuditLog::count());

        $rows = AuditLog::query()->orderBy('created_at')->orderBy('id')->get();
        $this->assertSame(
            ['security.failed_logins', 'settings.changed', 'payout.approved', 'book.approved', 'payment.verified'],
            $rows->pluck('action')->all()
        );

        $payout = $rows->firstWhere('action', 'payout.approved');
        $this->assertSame('Approved author payout ৳18,460', $payout->details['message']);
        $this->assertSame('2026-11-22 09:15:00', $payout->created_at->format('Y-m-d H:i:s'));
        $this->assertSame($this->superAdmin->id, $payout->actor_id);

        $bookRow = $rows->firstWhere('action', 'book.approved');
        $this->assertSame('Approved book: পদ্মা নদীর মাঝি', $bookRow->details['message']);
        $this->assertSame($book->id, $bookRow->subject_id);

        $system = $rows->firstWhere('action', 'security.failed_logins');
        $this->assertNull($system->actor_id);
        $this->assertSame('3 failed logins on Admin account — alert sent', $system->details['message']);
        $this->assertSame('2026-11-21', $system->created_at->format('Y-m-d'));

        // Re-running the seeder does not duplicate the five rows.
        (new AuditLogSeeder)->run();
        $this->assertSame(5, AuditLog::count());
    }

    // ---- coupons (§3.4) ----

    public function test_admin_can_create_coupon_and_limits_apply(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(CreateCoupon::class)
            ->set('data.code', 'ADMIN10')
            ->set('data.percent', 10)
            ->set('data.applies_to', 'all')
            ->set('data.is_active', true)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('coupons', ['code' => 'ADMIN10', 'percent' => 10]);

        // With a Super Admin limit set, a bigger discount is refused (A12).
        DB::table('settings')->updateOrInsert(['key' => 'promo.max_percent'], ['value' => '20']);

        Livewire::test(CreateCoupon::class)
            ->set('data.code', 'TOOBIG')
            ->set('data.percent', 50)
            ->set('data.applies_to', 'all')
            ->call('create')
            ->assertHasErrors(['percent']);
    }

    // ---- PayoutResource gates (§4.4) ----

    public function test_payout_resource_admin_403_super_admin_200(): void
    {
        $this->actingAs($this->admin)->get('/admin/payouts')->assertForbidden();
        $this->actingAs($this->superAdmin)->get('/admin/payouts')->assertOk();
    }

    public function test_payout_resource_authorization_responses(): void
    {
        $this->actingAs($this->admin);
        $this->assertFalse(PayoutResource::canViewAny());

        $this->actingAs($this->superAdmin);
        $this->assertTrue(PayoutResource::canViewAny());
    }

    public function test_support_cannot_open_payout_settings_or_order_pages(): void
    {
        $this->actingAs($this->support)->get('/admin/payouts')->assertForbidden();
        $this->actingAs($this->support)->get('/admin/settings')->assertForbidden();
        $this->actingAs($this->support)->get('/admin/orders')->assertForbidden();
        $this->actingAs($this->support)->get('/admin/support-tickets')->assertOk();
        $this->actingAs($this->support)->get('/admin/support-faqs')->assertOk();
    }

    // ---- panel access (§9.2) ----

    public function test_admin_pages_require_login_and_role(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs($this->reader)->get('/admin')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin')->assertOk();
        $this->actingAs($this->admin)->get('/admin/settings')->assertForbidden();
        $this->actingAs($this->superAdmin)->get('/admin/settings')->assertOk();
        $this->actingAs($this->admin)->get('/admin/audit-log')->assertForbidden();
        $this->actingAs($this->superAdmin)->get('/admin/audit-log')->assertOk();
    }

    // ---- settings + pool pages (§4.1, §4.2) ----

    public function test_settings_save_writes_db_and_audit(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(Settings::class)
            ->set('data.pool_share_pct', '80')
            ->set('data.pool_min_payout_bdt', '500')
            ->set('data.site_name', 'LearnVow')
            ->set('data.default_locale', 'bn')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('500', DB::table('settings')->where('key', 'pool.min_payout_bdt')->value('value'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.changed']);
    }

    public function test_pool_rules_save_writes_db(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(PoolRules::class)
            ->set('data.share_pct', '80')
            ->set('data.fee_rate_pct', '2')
            ->set('data.count_min_minutes', '10')
            ->set('data.count_min_percent', '10')
            ->set('data.min_payout_bdt', '500')
            ->set('data.payout_day', '10')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('2', DB::table('settings')->where('key', 'pool.fee_rate_pct')->value('value'));
    }

    public function test_audit_log_page_is_super_admin_only_and_read_only(): void
    {
        AdminService::writeAudit($this->admin, 'book.approved', 'books', 1, ['message' => 'x']);

        $this->actingAs($this->superAdmin);
        Livewire::test(AuditLogPage::class)->assertOk();

        $this->actingAs($this->admin)->get('/admin/audit-log')->assertForbidden();
    }

    // ---- refund queue (§3.4) ----

    public function test_refund_queue_admin_marks_under_review_super_admin_rejects(): void
    {
        $order = Order::create([
            'order_no' => 'LV-2026-2001', 'user_id' => $this->reader->id, 'type' => 'book',
            'status' => 'paid', 'subtotal_bdt' => 96, 'total_bdt' => 96, 'refund_status' => 'requested',
        ]);

        $this->service->markRefundUnderReview($order, $this->admin);
        $this->assertSame('under_review', $order->fresh()->refund_status);

        $this->service->rejectRefund($order, $this->superAdmin, 'No fault found');
        $this->assertSame('rejected', $order->fresh()->refund_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'refund.rejected', 'subject_id' => $order->id]);
    }

    public function test_admin_cannot_reject_refund(): void
    {
        $order = Order::create([
            'order_no' => 'LV-2026-2002', 'user_id' => $this->reader->id, 'type' => 'book',
            'status' => 'paid', 'subtotal_bdt' => 96, 'total_bdt' => 96, 'refund_status' => 'requested',
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service->rejectRefund($order, $this->admin);
    }
}
