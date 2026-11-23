<?php

namespace Tests\Feature\Moderation;

use App\Filament\Pages\Analytics;
use App\Filament\Pages\Backup;
use App\Filament\Resources\ReportResource\Pages\ListReports;
use App\Filament\Resources\ReportResource\Pages\ViewReport;
use App\Filament\Resources\SupportTicketResource\Pages\ListSupportTickets;
use App\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket;
use App\Filament\Widgets\AdminOrdersChartWidget;
use App\Livewire\SupportTicketForm;
use App\Livewire\TicketThread;
use App\Models\Author;
use App\Models\AuthorPayout;
use App\Models\Book;
use App\Models\Category;
use App\Models\Order;
use App\Models\Report;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Builder C — Phase 3 support ticket + moderation flows (spec §3.5, §5).
 * Reader opens a ticket, Support/Admin reply and escalate; the
 * controller serves Builder A's routes; Filament actions wire through.
 */
class ModerationFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private User $support;

    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'author', 'admin', 'super-admin', 'support'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        AdminService::assignRole($this->admin, 'admin');

        $this->superAdmin = User::factory()->create();
        AdminService::assignRole($this->superAdmin, 'super-admin');

        $this->support = User::factory()->create();
        AdminService::assignRole($this->support, 'support');

        $this->reader = User::factory()->create();
        AdminService::assignRole($this->reader, 'reader');

        // The real support/chat routes (Builder A, routes/author.php) are
        // already loaded in tests — hit them directly, no duplicates.
    }

    private function makeBook(): Book
    {
        $author = Author::create(['name' => 'Flow Author', 'slug' => 'flow-author-'.uniqid(), 'status' => 'approved']);
        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'flow-novel-'.uniqid()]);

        return Book::create([
            'author_id' => $author->id, 'category_id' => $category->id,
            'title' => 'Flow Book', 'slug' => 'flow-book-'.uniqid(),
            'description' => 'A flow test book.', 'language' => 'en',
            'price_bdt' => 96, 'list_price_bdt' => 120, 'status' => 'published',
        ]);
    }

    private function makeTicket(User $user, string $category = 'technical'): SupportTicket
    {
        $ticket = SupportTicket::create([
            'user_id' => $user->id, 'subject' => 'Help needed',
            'category' => $category, 'priority' => 'normal',
        ]);
        $ticket->messages()->create(['user_id' => $user->id, 'body' => 'Opening message.']);

        return $ticket;
    }

    // ---- ticket flow: open -> reply -> escalate (§3.5) ----

    public function test_reader_opens_ticket_payment_is_urgent(): void
    {
        $this->actingAs($this->reader);

        Livewire::test(SupportTicketForm::class)
            ->set('subject', 'Payment stuck')
            ->set('category', 'payment')
            ->set('body', 'I sent the money but nothing unlocked.')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('createdTicketNo', fn ($no) => (bool) preg_match('/^LV-T-\d{4}-\d{4}$/', (string) $no));

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame('urgent', $ticket->priority);
        $this->assertSame('open', $ticket->status);
        $this->assertSame('I sent the money but nothing unlocked.', $ticket->messages()->first()->body);
    }

    public function test_support_replies_and_escalates_to_admin(): void
    {
        $ticket = $this->makeTicket($this->reader);

        $this->actingAs($this->support);
        Livewire::test(TicketThread::class, ['ticket' => $ticket])
            ->set('body', 'We are looking into it.')
            ->call('reply')
            ->assertHasNoErrors();

        $this->assertSame('in_progress', $ticket->fresh()->status);
        $this->assertSame(2, $ticket->messages()->count());

        Livewire::test(TicketThread::class, ['ticket' => $ticket->fresh()])
            ->call('escalate', 'admin')
            ->assertHasNoErrors();

        $ticket = $ticket->fresh();
        $this->assertSame('escalated', $ticket->status);
        $this->assertSame('admin', $ticket->escalated_to);
        $this->assertNotNull($ticket->escalated_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'ticket.escalated', 'subject_id' => $ticket->id]);
    }

    public function test_reader_cannot_escalate_and_never_sees_internal_notes(): void
    {
        $ticket = $this->makeTicket($this->reader);
        $ticket->messages()->create([
            'user_id' => $this->support->id, 'body' => 'SECRET-INTERNAL', 'is_internal' => true,
        ]);

        $this->actingAs($this->reader);
        Livewire::test(TicketThread::class, ['ticket' => $ticket])
            ->assertDontSee('SECRET-INTERNAL')
            ->assertSee('Opening message.');

        // A reader escalating is denied: Livewire turns the
        // AuthorizationException into a 403 on the update request, so the
        // action never runs — the ticket stays open and unescalated.
        Livewire::test(TicketThread::class, ['ticket' => $ticket])->call('escalate', 'admin');

        $this->assertSame('open', $ticket->fresh()->status);
        $this->assertNull($ticket->fresh()->escalated_to);
    }

    public function test_support_ticket_list_loads_for_support_but_not_reader(): void
    {
        $this->makeTicket($this->reader);

        $this->actingAs($this->support);
        Livewire::test(ListSupportTickets::class)->assertOk();

        $this->actingAs($this->reader)->get('/admin/support-tickets')->assertForbidden();
    }

    public function test_filament_escalate_action_writes_escalation(): void
    {
        $ticket = $this->makeTicket($this->reader);

        $this->actingAs($this->support);
        Livewire::test(ViewSupportTicket::class, ['record' => $ticket->id])
            ->callAction('escalate', ['escalated_to' => 'super_admin'])
            ->assertHasNoErrors();

        $this->assertSame('super_admin', $ticket->fresh()->escalated_to);
        $this->assertSame('escalated', $ticket->fresh()->status);
    }

    // ---- controller (§9.1) ----

    public function test_support_chat_controller_open_thread_reply(): void
    {
        $this->get('bn/support/chat')->assertRedirect('/bn/login');

        $this->actingAs($this->reader)
            ->get('bn/support/chat')
            ->assertOk();

        $this->actingAs($this->reader)
            ->post('bn/support/chat', [
                'subject' => 'Book missing',
                'category' => 'book',
                'body' => 'My purchased book is not in the library.',
            ])
            ->assertRedirect();

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame('normal', $ticket->priority);

        $this->actingAs($this->reader)
            ->get('bn/support/chat/'.$ticket->id)
            ->assertOk()
            ->assertSee($ticket->ticket_no);

        $other = User::factory()->create();
        $this->actingAs($other)->get('bn/support/chat/'.$ticket->id)->assertForbidden();

        $this->actingAs($this->reader)
            ->post('bn/support/chat/'.$ticket->id.'/reply', ['body' => 'Any update?'])
            ->assertRedirect();

        $this->assertSame(2, $ticket->messages()->count());
    }

    // ---- Filament report actions (§5.2) ----

    public function test_filament_hide_book_action_and_admin_dismiss(): void
    {
        $book = $this->makeBook();
        $report = Report::create([
            'reporter_id' => $this->reader->id, 'reportable_type' => 'books',
            'reportable_id' => $book->id, 'reason' => 'spam',
        ]);

        $this->actingAs($this->support);
        Livewire::test(ListReports::class)
            ->callTableAction('hide_book', $report->id)
            ->assertHasNoErrors();

        $this->assertNotNull($book->fresh()->hidden_at);
        $this->assertSame('reviewing', $report->fresh()->status);

        $this->actingAs($this->admin);
        Livewire::test(ViewReport::class, ['record' => $report->id])
            ->callAction('decide_dismiss', ['note' => 'False alarm'])
            ->assertHasNoErrors();

        $this->assertSame('dismissed', $report->fresh()->status);
        $this->assertNull($book->fresh()->hidden_at);
    }

    // ---- dashboard chart + analytics (§3.1, §4.4, A18) ----

    public function test_orders_chart_uses_live_counts(): void
    {
        foreach ([0, 0, 3] as $daysAgo) {
            $order = Order::create([
                'order_no' => 'LV-2026-'.uniqid(), 'user_id' => $this->reader->id,
                'type' => 'book', 'status' => 'paid', 'subtotal_bdt' => 96,
                'total_bdt' => 96, 'refund_status' => 'none',
            ]);
            $order->created_at = now()->subDays($daysAgo);
            $order->save();
        }

        // Round 2: the orders chart is Admin / Super Admin only
        // (AdminOrdersChartWidget::canView()), so the widget must be
        // mounted as an admin — Support/anonymous mounts are refused.
        $this->actingAs($this->admin);

        $testable = Livewire::test(AdminOrdersChartWidget::class)->assertOk();
        $getData = \Closure::bind(fn () => $this->getData(), $testable->instance(), $testable->instance());
        $data = $getData();

        $this->assertSame(now()->format('d M'), $data['labels'][6]);
        $this->assertSame(2, $data['datasets'][0]['data'][6]); // today: 2 orders
        $this->assertSame(1, $data['datasets'][0]['data'][3]); // 3 days ago: 1 order
    }

    public function test_analytics_figures_are_live_not_model_figures(): void
    {
        Order::create([
            'order_no' => 'LV-2026-3001', 'user_id' => $this->reader->id,
            'type' => 'premium', 'status' => 'paid', 'subtotal_bdt' => 99,
            'total_bdt' => 99, 'refund_status' => 'none',
        ]);
        Order::create([
            'order_no' => 'LV-2026-3002', 'user_id' => $this->reader->id,
            'type' => 'book', 'status' => 'paid', 'subtotal_bdt' => 96,
            'total_bdt' => 96, 'refund_status' => 'none',
        ]);

        $figures = Analytics::figures();

        $this->assertSame(99, $figures['subscriptions_revenue']);
        $this->assertSame(96, $figures['book_sales_revenue']);
        $this->assertSame(195, $figures['total_revenue']);
        // The prototype's modelled figures must never appear.
        $this->assertNotSame(186000, $figures['subscriptions_revenue']);
        $this->assertNotSame(98400, $figures['book_sales_revenue']);
    }

    // ---- backup CSVs (§4.6) ----

    public function test_backup_csvs_never_include_full_bkash_or_trxid(): void
    {
        $order = Order::create([
            'order_no' => 'LV-2026-4001', 'user_id' => $this->reader->id,
            'type' => 'book', 'status' => 'paid', 'subtotal_bdt' => 96,
            'total_bdt' => 96, 'refund_status' => 'none',
        ]);

        $author = Author::create([
            'user_id' => $this->reader->id, 'name' => 'CSV Author',
            'slug' => 'csv-author', 'status' => 'approved',
        ]);
        AuthorPayout::create([
            'author_id' => $author->id, 'period' => '2026-11',
            'sales_bdt' => 1000, 'pool_bdt' => 0, 'total_bdt' => 1000,
            'method' => 'bkash', 'destination' => '01700000210', 'status' => 'pending',
        ]);

        $ordersCsv = Backup::ordersCsv();
        $this->assertStringContainsString('LV-2026-4001', $ordersCsv);
        $this->assertStringNotContainsString('9HX7K2LMNP', $ordersCsv);

        $payoutsCsv = Backup::payoutsCsv();
        $this->assertStringContainsString('··210', $payoutsCsv);
        $this->assertStringNotContainsString('01700000210', $payoutsCsv);

        $this->assertTrue(Backup::backupAvailable());
    }
}
