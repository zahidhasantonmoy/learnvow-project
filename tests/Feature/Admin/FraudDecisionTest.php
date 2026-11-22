<?php

namespace Tests\Feature\Admin;

use App\Filament\Pages\FraudAlert;
use App\Models\FraudAlert as FraudAlertModel;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Builder C — Phase 2 fraud decisions (spec §3.3, §6.6).
 *
 * Depends on Builder B: FraudAlert/Payment/Order models and
 * BillingService::decideFraud(). No access is granted until a decision
 * is made; the duplicate_suspect payment stays blocked.
 */
class FraudDecisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $reader;

    private FraudAlertModel $alert;

    private Payment $duplicatePayment;

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

        $firstOrder = (new Order)->forceFill([
            'order_no' => 'LV-2026-1041',
            'user_id' => $this->reader->id,
            'type' => 'book',
            'status' => 'paid',
            'subtotal_bdt' => 150,
            'total_bdt' => 150,
        ]);
        $firstOrder->save();

        $firstPayment = (new Payment)->forceFill([
            'order_id' => $firstOrder->id,
            'user_id' => $this->reader->id,
            'method' => 'bkash',
            'trx_id' => '9HX7K2LMNP',
            'trx_id_normalized' => '9HX7K2LMNP',
            'amount_bdt' => 150,
            'status' => 'verified',
            'submitted_at' => now()->subDay(),
        ]);
        $firstPayment->save();

        $secondOrder = (new Order)->forceFill([
            'order_no' => 'LV-2026-1044',
            'user_id' => $this->reader->id,
            'type' => 'book',
            'status' => 'duplicate_suspect',
            'subtotal_bdt' => 150,
            'total_bdt' => 150,
        ]);
        $secondOrder->save();

        $this->duplicatePayment = (new Payment)->forceFill([
            'order_id' => $secondOrder->id,
            'user_id' => $this->reader->id,
            'method' => 'bkash',
            'trx_id' => '9hx7k2lmnp',
            'trx_id_normalized' => '9HX7K2LMNP',
            'amount_bdt' => 150,
            'status' => 'duplicate_suspect',
            'submitted_at' => now(),
        ]);
        $this->duplicatePayment->save();

        $this->alert = (new FraudAlertModel)->forceFill([
            'trx_id_normalized' => '9HX7K2LMNP',
            'payment_id' => $this->duplicatePayment->id,
            'existing_payment_id' => $firstPayment->id,
            'status' => 'open',
        ]);
        $this->alert->save();
    }

    public function test_admin_can_decide_confirmed_fraud_with_a_note(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FraudAlert::class)
            ->callTableAction('decide', $this->alert->id, data: [
                'decision' => 'confirmed_fraud',
                'decision_note' => 'Same TrxID reused for a second order.',
            ]);

        $alert = $this->alert->fresh();

        $this->assertSame('confirmed_fraud', $alert->status);
        $this->assertSame($this->admin->id, $alert->decided_by);
        $this->assertSame('Same TrxID reused for a second order.', $alert->decision_note);
        // The suspect payment is rejected on a confirmed-fraud decision —
        // blocked, no access granted, order failed.
        $this->assertSame('rejected', $this->duplicatePayment->fresh()->status);
        $this->assertSame('failed', \App\Models\Order::find($this->duplicatePayment->order_id)->status);
    }

    public function test_admin_can_decide_mistake(): void
    {
        $this->actingAs($this->admin);

        Livewire::test(FraudAlert::class)
            ->callTableAction('decide', $this->alert->id, data: [
                'decision' => 'mistake',
                'decision_note' => 'User submitted the same TrxID twice by accident.',
            ]);

        $this->assertSame('mistake', $this->alert->fresh()->status);
    }

    public function test_reader_cannot_open_the_fraud_page(): void
    {
        $this->actingAs($this->reader)
            ->get('/admin/fraud-alerts')
            ->assertForbidden();
    }

    public function test_admin_can_open_the_fraud_page(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/fraud-alerts')
            ->assertOk();
    }
}
