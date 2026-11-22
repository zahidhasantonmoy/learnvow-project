<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\PayConfig;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Models\Order;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Builder C — Phase 2 role gates + refund visibility (spec §3.6).
 *
 * - PayConfig is SUPER ADMIN ONLY: Admin gets 403, Super Admin gets 200
 *   and can save settings (settings table, spec §6.21 — Builder B).
 * - Refund: visible on the order detail ("under review — admin reviews
 *   within 3 working days"); approval is Super Admin only.
 */
class PayConfigAndRefundTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        AdminService::assignRole($this->admin, 'admin');

        $this->superAdmin = User::factory()->create();
        AdminService::assignRole($this->superAdmin, 'super-admin');

        $this->reader = User::factory()->create();
        AdminService::assignRole($this->reader, 'reader');
    }

    public function test_admin_gets_403_on_pay_config(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/pay-config')
            ->assertForbidden();
    }

    public function test_reader_gets_403_on_pay_config(): void
    {
        $this->actingAs($this->reader)
            ->get('/admin/pay-config')
            ->assertForbidden();
    }

    public function test_super_admin_can_open_pay_config(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/pay-config')
            ->assertOk();
    }

    public function test_super_admin_can_save_pay_config_settings(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(PayConfig::class)
            ->set('data.bkash_number', '01711111111')
            ->set('data.nagad_number', '01822222222')
            ->set('data.subscription_price_bdt', 99)
            ->set('data.sla_hours', 12)
            ->call('save');

        $this->assertSame('01711111111', \Illuminate\Support\Facades\DB::table('settings')->where('key', 'payment.bkash_number')->value('value'));
        $this->assertSame('99', \Illuminate\Support\Facades\DB::table('settings')->where('key', 'subscription.price_bdt')->value('value'));
    }

    public function test_refund_request_is_visible_on_order_detail(): void
    {
        $order = (new Order)->forceFill([
            'order_no' => 'LV-2026-1050',
            'user_id' => $this->reader->id,
            'type' => 'book',
            'status' => 'paid',
            'subtotal_bdt' => 120,
            'total_bdt' => 96,
            'refund_requested_at' => now(),
            'refund_status' => 'requested',
        ]);
        $order->save();

        $this->actingAs($this->admin)
            ->get('/admin/orders/'.$order->id)
            ->assertOk()
            ->assertSee(__('admin.orders.refund_under_review_note'));
    }

    public function test_admin_cannot_approve_a_refund_but_super_admin_can_see_the_action(): void
    {
        $order = (new Order)->forceFill([
            'order_no' => 'LV-2026-1051',
            'user_id' => $this->reader->id,
            'type' => 'book',
            'status' => 'paid',
            'subtotal_bdt' => 120,
            'total_bdt' => 96,
            'refund_requested_at' => now(),
            'refund_status' => 'requested',
        ]);
        $order->save();

        $this->actingAs($this->admin);
        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->assertActionHidden('approveRefund')
            ->assertActionVisible('markRefundUnderReview');

        $this->actingAs($this->superAdmin);
        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->assertActionVisible('approveRefund');
    }

    public function test_admin_can_open_orders_transactions_and_subscriptions_lists(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/transactions')->assertOk();
        $this->get('/admin/subscriptions')->assertOk();
    }
}
