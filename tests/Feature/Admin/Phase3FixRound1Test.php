<?php

namespace Tests\Feature\Admin;

use App\Filament\Widgets\AdminStatsWidget;
use App\Livewire\CheckoutForm;
use App\Livewire\SupportTicketForm;
use App\Models\Author;
use App\Models\AuthorApplication;
use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Models\Book;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Report;
use App\Models\Review;
use App\Models\Setting;
use App\Models\SupportTicket;
use App\Models\TakedownRequest;
use App\Models\User;
use App\Services\AdminService;
use App\Services\BillingService;
use App\Services\PayoutService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Fix round 1 (QA 5/10 — NOT READY): one test per fixed behaviour.
 * C1–C7, M1–M6 and the four minors.
 */
class Phase3FixRound1Test extends TestCase
{
    use RefreshDatabase;

    private AdminService $adminService;

    private BillingService $billing;

    private PayoutService $payout;

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

        $this->adminService = app(AdminService::class);
        $this->billing = app(BillingService::class);
        $this->payout = app(PayoutService::class);
    }

    // ---- C1: Analytics page renders (was 500, wrong Placeholder namespace) ----

    public function test_c1_analytics_page_renders_for_super_admin(): void
    {
        $this->actingAs($this->superAdmin)->get('/admin/analytics')
            ->assertOk()
            ->assertSee(__('admin.analytics.heading'));
    }

    // ---- C2/C3: Audit Log + Fraud Alerts tables render rows ----

    public function test_c2_audit_log_page_renders_table_with_rows(): void
    {
        AdminService::writeAudit($this->superAdmin, 'payout.approved', 'author_payouts', 1, [
            'message' => 'Approved author payout ৳18,460',
        ]);

        $this->actingAs($this->superAdmin)->get('/admin/audit-log')
            ->assertOk()
            ->assertSee('<table', false)
            ->assertSee('Approved author payout');
    }

    public function test_c3_fraud_alerts_page_renders_table_with_rows(): void
    {
        // A duplicate TrxID across two orders opens one fraud alert.
        $priced = $this->billing->priceOrder([
            'user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id,
        ]);
        $order1 = $this->billing->createOrder($this->reader, $priced);
        $this->billing->submitTrxId($order1, 'bkash', 'TRX1234567');

        $order2 = $this->billing->createOrder($this->reader, $priced);
        $this->billing->submitTrxId($order2, 'bkash', 'TRX1234567');

        $this->assertDatabaseHas('fraud_alerts', ['status' => 'open']);

        $this->actingAs($this->admin)->get('/admin/fraud-alerts')
            ->assertOk()
            ->assertSee('<table', false)
            ->assertSee('TRX1234567');
    }

    // ---- C4: notifications fire on the flows actually used + header bell ----

    public function test_c4_approving_a_book_notifies_its_author(): void
    {
        $book = Book::create([
            'author_id' => $this->author->id, 'category_id' => Category::first()->id,
            'title' => 'New Book', 'slug' => 'new-book', 'description' => 'New.',
            'language' => 'en', 'price_bdt' => 100, 'status' => 'in_review',
        ]);

        $this->adminService->approveBook($book, $this->admin);

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $this->authorUser->id,
            'type' => 'App\Notifications\AuthorNotification',
        ]);
        $note = $this->authorUser->fresh()->notifications()->first();
        $this->assertSame('book_approved', $note->data['type']);
        $this->assertSame($book->id, $note->data['book_id']);
    }

    public function test_c4_approving_an_author_notifies_and_closes_the_application(): void
    {
        $applicant = User::factory()->create(['phone' => '01700000007']);
        $applicant->assignRole('reader');
        $pendingAuthor = Author::create([
            'name' => 'Applicant', 'slug' => 'applicant',
            'user_id' => $applicant->id, 'status' => 'pending',
        ]);
        AuthorApplication::create([
            'user_id' => $applicant->id, 'author_id' => $pendingAuthor->id,
            'bkash_number' => '01700000007', 'status' => 'submitted',
        ]);

        $this->adminService->approveAuthor($pendingAuthor, $this->admin);

        $this->assertSame('approved', AuthorApplication::where('user_id', $applicant->id)->first()->status);
        $note = $applicant->fresh()->notifications()->first();
        $this->assertNotNull($note);
        $this->assertSame('application_approved', $note->data['type']);
    }

    public function test_c4_payout_release_notifies_the_author(): void
    {
        $payout = AuthorPayout::create([
            'author_id' => $this->author->id, 'period' => '2026-10',
            'sales_bdt' => 600, 'pool_bdt' => 0, 'total_bdt' => 600,
            'method' => 'bkash', 'status' => 'approved',
            'approved_by' => $this->superAdmin->id, 'approved_at' => now(),
        ]);

        $this->payout->releasePayout($payout, $this->superAdmin, 'TRX1234567');

        $note = $this->authorUser->fresh()->notifications()->first();
        $this->assertNotNull($note);
        $this->assertSame('payout_released', $note->data['type']);
        $this->assertArrayNotHasKey('payout_trx_id', $note->data);
    }

    public function test_c4_verified_payment_notifies_the_buyer_order_live(): void
    {
        $priced = $this->billing->priceOrder([
            'user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id,
        ]);
        $order = $this->billing->createOrder($this->reader, $priced);
        $payment = $this->billing->submitTrxId($order, 'bkash', 'TRX1234567');

        $this->billing->verifyPayment($payment, $this->admin);

        $note = $this->reader->fresh()->notifications()->first();
        $this->assertNotNull($note);
        $this->assertSame('order_live', $note->data['type']);
        $this->assertSame($order->order_no, $note->data['order_no']);
    }

    public function test_c4_header_bell_links_to_notifications_with_unread_count(): void
    {
        $this->authorUser->notify(new \App\Notifications\AuthorNotification('book_approved', ['book_id' => $this->prophet->id]));

        $this->actingAs($this->authorUser)->get('/bn/')
            ->assertOk()
            ->assertSee(route('notifications.index', ['locale' => 'bn']), false)
            ->assertSee('(1)');
    }

    // ---- C5: close period via Artisan command ----

    public function test_c5_payouts_close_command_creates_payout_above_minimum(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-05 10:00:00'));

        AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => $this->prophet->id,
            'period' => '2026-11', 'type' => 'sale', 'amount_bdt' => 600, 'share_pct' => 80,
        ]);

        $exit = Artisan::call('payouts:close', ['period' => '2026-11']);

        $this->assertSame(0, $exit);
        $this->assertDatabaseHas('author_payouts', [
            'author_id' => $this->author->id, 'period' => '2026-11',
            'status' => 'pending', 'total_bdt' => 600,
        ]);

        // Closing twice is refused.
        $this->assertSame(1, Artisan::call('payouts:close', ['period' => '2026-11']));

        Carbon::setTestNow();
    }

    // ---- C6: wallet-use control buys fully with wallet, author earns ৳77 ----

    public function test_c6_wallet_checkbox_buys_prophet_fully_with_wallet(): void
    {
        $this->billing->walletTopup($this->reader->fresh(), 200, 'test', null, 'Test top-up');
        $this->reader->refresh(); // actingAs uses the given instance: re-read the topped-up balance.

        $this->actingAs($this->reader);

        Livewire::test(CheckoutForm::class, ['type' => 'book', 'bookId' => $this->prophet->id])
            ->set('useWallet', true)
            ->assertSee('৳0')
            ->call('submit')
            ->assertRedirect();

        $order = \App\Models\Order::where('user_id', $this->reader->id)->latest()->firstOrFail();
        $this->assertSame('paid', $order->status);
        $this->assertSame(96, (int) $order->wallet_used_bdt);

        $earning = AuthorEarning::where('order_id', $order->id)->where('type', 'sale')->first();
        $this->assertNotNull($earning);
        $this->assertSame(77, (int) $earning->amount_bdt);
    }

    // ---- C7: user edit form saves with NULL email ----

    public function test_c7_user_edit_saves_with_null_email(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(\App\Filament\Resources\UserResource\Pages\EditUser::class, [
            'record' => $this->reader->getRouteKey(),
        ])
            ->set('data.name', 'Demo Reader Renamed')
            ->set('data.email', null)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Demo Reader Renamed', $this->reader->fresh()->name);
        $this->assertNull($this->reader->fresh()->email);
    }

    // ---- M1: report a book/review + file a takedown claim ----

    public function test_m1_logged_in_user_can_report_a_book(): void
    {
        $this->actingAs($this->reader)
            ->post('/bn/books/the-prophet/report', ['reason' => 'spam', 'details' => 'Looks copied.'])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $this->reader->id,
            'reportable_type' => 'books',
            'reportable_id' => $this->prophet->id,
            'reason' => 'spam', 'status' => 'open',
        ]);
    }

    public function test_m1_logged_in_user_can_report_a_review(): void
    {
        $review = Review::create([
            'book_id' => $this->prophet->id, 'user_id' => $this->reader->id,
            'rating' => 5, 'body' => 'Great book.', 'status' => 'approved',
        ]);

        $this->actingAs($this->reader)
            ->post('/bn/reviews/'.$review->id.'/report', ['reason' => 'abuse'])
            ->assertRedirect();

        $this->assertDatabaseHas('reports', [
            'reportable_type' => 'reviews', 'reportable_id' => $review->id, 'reason' => 'abuse',
        ]);
    }

    public function test_m1_guest_can_file_a_takedown_claim_with_48h_target(): void
    {
        $this->get('/bn/takedown')->assertOk()->assertSee(__('moderation.takedowns.file_claim'));

        $this->post('/bn/takedown', [
            'book_id' => $this->prophet->id,
            'claimant_name' => 'Rights Holder',
            'claimant_email' => 'holder@example.com',
            'reason' => 'This book infringes my copyright.',
        ])->assertRedirect();

        $claim = TakedownRequest::where('book_id', $this->prophet->id)->firstOrFail();
        $this->assertSame('open', $claim->status);
        $this->assertNotNull($claim->deadline_at);
        $this->assertTrue($claim->deadline_at->greaterThan(now()->addHours(47)));
    }

    // ---- M2: taken-down book restores via approveBook ----

    public function test_m2_taken_down_book_can_be_restored(): void
    {
        $this->prophet->update(['status' => 'taken_down']);

        $this->adminService->approveBook($this->prophet->fresh(), $this->admin);

        $this->assertSame('published', $this->prophet->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'book.approved', 'subject_type' => 'books', 'subject_id' => $this->prophet->id,
        ]);
    }

    // ---- M3: available balance is computed; over-balance release is visible ----

    public function test_m3_available_balance_accounts_for_paid_payouts(): void
    {
        AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => $this->prophet->id,
            'period' => '2026-11', 'type' => 'sale', 'amount_bdt' => 49276, 'share_pct' => 80,
        ]);
        AuthorPayout::create([
            'author_id' => $this->author->id, 'period' => '2026-09',
            'sales_bdt' => 11980, 'pool_bdt' => 0, 'total_bdt' => 11980,
            'method' => 'bkash', 'status' => 'paid',
        ]);
        AuthorPayout::create([
            'author_id' => $this->author->id, 'period' => '2026-10',
            'sales_bdt' => 15230, 'pool_bdt' => 0, 'total_bdt' => 15230,
            'method' => 'bkash', 'status' => 'paid',
        ]);

        // ৳49,276 earned − ৳27,210 paid = ৳22,066 available: the seeded
        // Nov payout (৳49,276) exceeds it — the release UI must show this.
        $this->assertSame(22066, $this->payout->authorBalance($this->author));

        $payout = AuthorPayout::create([
            'author_id' => $this->author->id, 'period' => '2026-11',
            'sales_bdt' => 49276, 'pool_bdt' => 0, 'total_bdt' => 49276,
            'method' => 'bkash', 'status' => 'approved',
            'approved_by' => $this->superAdmin->id, 'approved_at' => now(),
        ]);

        $this->actingAs($this->superAdmin)->get('/admin/payouts/'.$payout->id)
            ->assertOk()
            ->assertSee('22,066');
    }

    // ---- M4: Super Admin guards are validation errors, not 500s ----

    public function test_m4_second_super_admin_guard_is_a_validation_error(): void
    {
        $other = User::factory()->create();

        try {
            AdminService::assignRole($other, 'super-admin');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('roles', $e->errors());
        }
    }

    public function test_m4_sole_super_admin_removal_guard_is_a_validation_error(): void
    {
        try {
            AdminService::removeRole($this->superAdmin, 'super-admin');
            $this->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('roles', $e->errors());
        }
    }

    // ---- M5: dashboard stats scoped by role (widget-level, exact labels) ----

    /** @return string[] */
    private function widgetStatLabels(User $user): array
    {
        $this->actingAs($user);
        $widget = new AdminStatsWidget();
        $method = new \ReflectionMethod($widget, 'getStats');
        $method->setAccessible(true);

        return array_map(fn ($stat) => $stat->getLabel(), $method->invoke($widget));
    }

    public function test_m5_admin_dashboard_hides_payout_stats_super_admin_sees_them(): void
    {
        $this->assertNotContains(
            __('admin.stats.payouts_pending'),
            $this->widgetStatLabels($this->admin)
        );
        $this->assertContains(
            __('admin.stats.payouts_pending'),
            $this->widgetStatLabels($this->superAdmin)
        );
        $this->assertContains(
            __('admin.stats.payments_pending'),
            $this->widgetStatLabels($this->admin)
        );
    }

    public function test_m5_support_dashboard_shows_only_ticket_stats(): void
    {
        $this->assertSame(
            [__('admin.stats.support_open')],
            $this->widgetStatLabels($this->support)
        );
    }

    // ---- M6: Bengali publish validation + formatted earnings ----

    public function test_m6_small_cover_error_is_bengali_on_bn_pages(): void
    {
        app()->setLocale('bn');
        $this->actingAs($this->authorUser);

        Livewire::test(\App\Livewire\AuthorUpload::class)
            ->set('title', 'Test Book Title')
            ->set('description', 'A test book description.')
            ->set('language', 'en')
            ->set('categoryId', Category::first()->id)
            ->set('priceBdt', 150)
            ->set('bookFile', UploadedFile::fake()->create('book.epub', 100, 'application/epub+zip'))
            ->set('cover', UploadedFile::fake()->image('small.png', 800, 600))
            ->call('submit')
            ->assertHasErrors(['cover'])
            ->assertSee(__('author.publish_cover_dimensions'));
    }

    public function test_m6_earnings_page_formats_amounts_with_separators(): void
    {
        AuthorEarning::create([
            'author_id' => $this->author->id, 'book_id' => $this->prophet->id,
            'period' => '2026-11', 'type' => 'sale', 'amount_bdt' => 48640, 'share_pct' => 80,
        ]);

        $this->actingAs($this->authorUser)->get('/bn/author/earnings')
            ->assertOk()
            ->assertSee('48,640')
            ->assertDontSee('৳48640');
    }

    // ---- Minors ----

    public function test_minor_ticket_form_redirects_to_the_new_thread(): void
    {
        $this->actingAs($this->reader);

        Livewire::test(SupportTicketForm::class)
            ->set('subject', 'Payment stuck')
            ->set('category', 'payment')
            ->set('body', 'I paid but the book is not unlocked yet.')
            ->call('submit')
            ->assertRedirect();

        $ticket = SupportTicket::where('user_id', $this->reader->id)->firstOrFail();
        $this->assertSame('LV-T-'.now()->format('Y').'-0001', $ticket->ticket_no);
    }

    public function test_minor_admin_pages_carry_noindex(): void
    {
        $this->actingAs($this->superAdmin)->get('/admin')
            ->assertOk()
            ->assertSee('noindex', false);
    }

    public function test_minor_publish_page_redirects_reader_without_application(): void
    {
        $this->actingAs($this->reader)->get('/bn/author/publish')
            ->assertRedirect(route('author.apply', ['locale' => 'bn']));
    }
}
