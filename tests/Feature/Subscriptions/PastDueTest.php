<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\AdminService;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PastDueTest extends TestCase
{
    use RefreshDatabase;

    private BillingService $billing;

    private User $reader;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->plan = SubscriptionPlan::create([
            'slug' => 'premium-monthly', 'name' => 'LearnVow Premium', 'price_bdt' => 99,
            'duration_days' => 30, 'trial_days' => 7, 'is_active' => true,
        ]);

        $this->reader = User::factory()->create(['phone' => '01700000002']);
        AdminService::assignRole($this->reader, 'reader');

        $this->billing = app(BillingService::class);
    }

    private function subscription(string $status, string $endsAt, ?string $trialEndsAt = null): Subscription
    {
        return Subscription::create([
            'user_id' => $this->reader->id, 'plan_id' => $this->plan->id, 'order_id' => null,
            'status' => $status, 'starts_at' => now()->subDays(31),
            'ends_at' => $endsAt, 'trial_ends_at' => $trialEndsAt,
        ]);
    }

    public function test_active_overdue_flips_to_past_due_and_notifies_once(): void
    {
        $sub = $this->subscription('active', now()->subDay()->toDateTimeString());

        $this->artisan('subscriptions:mark-past-due')->assertExitCode(0);
        $this->assertSame('past_due', $sub->fresh()->status);
        $this->assertNotNull($sub->fresh()->past_due_at);
        $this->assertFalse($this->billing->hasActivePremium($this->reader->fresh()));

        $count = $this->reader->notifications()->count();
        $this->assertSame(1, $count);

        // Idempotent: a second run flips nothing and notifies nobody twice.
        $this->artisan('subscriptions:mark-past-due')->assertExitCode(0);
        $this->assertSame('past_due', $sub->fresh()->status);
        $this->assertSame(1, $this->reader->notifications()->count());
    }

    public function test_trial_overdue_flips_to_expired_not_past_due(): void
    {
        $sub = $this->subscription('trialing', now()->subDay()->toDateTimeString(), now()->subDay()->toDateTimeString());

        $this->artisan('subscriptions:mark-past-due')->assertExitCode(0);
        $this->assertSame('expired', $sub->fresh()->status);
        $this->assertNull($sub->fresh()->past_due_at);
    }

    public function test_not_yet_ended_subscription_is_untouched(): void
    {
        $sub = $this->subscription('active', now()->addDays(5)->toDateTimeString());

        $this->artisan('subscriptions:mark-past-due')->assertExitCode(0);
        $this->assertSame('active', $sub->fresh()->status);
        $this->assertSame(0, $this->reader->notifications()->count());
    }

    public function test_renewal_reactivates_past_due(): void
    {
        $sub = $this->subscription('active', now()->subDay()->toDateTimeString());
        $this->artisan('subscriptions:mark-past-due')->assertExitCode(0);
        $this->assertSame('past_due', $sub->fresh()->status);

        // Renewal: a paid premium order activates a fresh term.
        $priced = $this->billing->priceOrder(['user' => $this->reader, 'type' => 'premium', 'plan_id' => $this->plan->id]);
        $order = $this->billing->createOrder($this->reader, $priced);
        $order->update(['status' => 'paid']);
        $new = $this->billing->activateSubscription($order);

        $this->assertSame('active', $new->status);
        $this->assertSame('expired', $sub->fresh()->status);
        $this->assertTrue($this->billing->hasActivePremium($this->reader->fresh()));
    }

    public function test_gateway_resolver_defaults_to_manual(): void
    {
        $gateway = $this->billing->gateway();
        $this->assertSame('manual', $gateway->name());
        $this->assertFalse($gateway->supportsRefunds());
        $this->assertFalse($gateway->verifyCallback([]));

        \DB::table('settings')->updateOrInsert(['key' => 'payments.gateway'], ['value' => 'sslcommerz']);
        $this->assertSame('manual', $this->billing->gateway()->name());
    }

    public function test_international_plan_is_199(): void
    {
        SubscriptionPlan::create([
            'slug' => 'premium-monthly-intl', 'name' => 'Premium International', 'price_bdt' => 199,
            'duration_days' => 30, 'trial_days' => 7, 'is_active' => true,
        ]);

        $intl = $this->billing->internationalPlan();
        $this->assertNotNull($intl);
        $this->assertSame(199, (int) $intl->price_bdt);
        $this->assertSame(99, (int) $this->billing->premiumPlan()->price_bdt);
    }
}
