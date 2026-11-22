<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Resources\PaymentResource\Pages\ViewPayment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Builder C — Phase 2 payment verification (spec §3.1, §3.6).
 *
 * Depends on Builder B: Order/Payment models (§6.2, §6.4) and
 * BillingService::verifyPayment() / rejectPayment(). Until those land,
 * these tests fail with "class/table not found" — that is the Builder B
 * dependency, not a failure of this file.
 */
class PaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $reader;

    private Order $order;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        AdminService::assignRole($this->admin, 'admin');

        $this->reader = User::factory()->create();
        AdminService::assignRole($this->reader, 'reader');

        $this->order = (new Order)->forceFill([
            'order_no' => 'LV-2026-1043',
            'user_id' => $this->reader->id,
            'type' => 'book',
            'status' => 'awaiting_verification',
            'subtotal_bdt' => 96,
            'coupon_amt_bdt' => 19,
            'wallet_used_bdt' => 0,
            'total_bdt' => 77,
        ]);
        $this->order->save();

        $this->payment = (new Payment)->forceFill([
            'order_id' => $this->order->id,
            'user_id' => $this->reader->id,
            'method' => 'bkash',
            'trx_id' => '9HW3PZ77AB',
            'trx_id_normalized' => '9HW3PZ77AB',
            'amount_bdt' => 77,
            'status' => 'awaiting_verification',
            'submitted_at' => now(),
            'fix_count' => 0,
        ]);
        $this->payment->save();
    }

    public function test_admin_can_verify_a_payment_and_order_becomes_paid(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ViewPayment::class, ['record' => $this->payment->id])
            ->callAction('verify');

        $this->assertSame('verified', $this->payment->fresh()->status);
        $this->assertSame('paid', $this->order->fresh()->status);
        $this->assertNotNull($this->payment->fresh()->verified_at);
        $this->assertSame($this->admin->id, $this->payment->fresh()->verified_by);
    }

    public function test_admin_can_reject_a_payment_with_a_note_and_order_fails(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(ViewPayment::class, ['record' => $this->payment->id])
            ->callAction('reject', data: ['admin_note' => 'TrxID does not match our bKash account.']);

        $payment = $this->payment->fresh();

        $this->assertSame('rejected', $payment->status);
        $this->assertSame('failed', $this->order->fresh()->status);
        $this->assertSame('TrxID does not match our bKash account.', $payment->admin_note);
    }

    public function test_verify_action_is_hidden_once_payment_is_verified(): void
    {
        $this->payment->forceFill(['status' => 'verified'])->save();

        $this->actingAs($this->admin);

        Livewire::test(ViewPayment::class, ['record' => $this->payment->id])
            ->assertActionHidden('verify')
            ->assertActionHidden('reject');
    }

    public function test_reader_cannot_open_the_payments_list(): void
    {
        $this->actingAs($this->reader)
            ->get('/admin/payments')
            ->assertForbidden();
    }

    public function test_admin_can_open_the_payments_list(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/payments')
            ->assertOk();
    }

    public function test_delayed_badge_after_twelve_hours(): void
    {
        $this->payment->forceFill(['submitted_at' => now()->subHours(13)])->save();

        $this->assertTrue(\App\Filament\Resources\PaymentResource::isDelayed($this->payment->fresh()));

        $fresh = $this->payment->fresh();
        $fresh->forceFill(['submitted_at' => now()->subHours(2)])->save();

        $this->assertFalse(\App\Filament\Resources\PaymentResource::isDelayed($fresh->fresh()));
    }
}
