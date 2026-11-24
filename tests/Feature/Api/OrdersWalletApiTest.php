<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Subscription;
use App\Models\UserBook;

class OrdersWalletApiTest extends ApiTestCase
{
    public function test_api_order_blocked_when_book_owned_via_purchase(): void
    {
        // AUD3-07 (API follow-up): same rule as the web checkout — a
        // book owned via PURCHASE cannot be bought again via the API.
        UserBook::create([
            'user_id' => $this->reader->id, 'book_id' => $this->prophet->id, 'source' => 'purchase',
        ]);
        $this->asReader();

        $this->postJson('/api/v1/orders', [
            'type' => 'book', 'book_id' => $this->prophet->id,
        ])->assertStatus(422)
            ->assertJsonPath('message', 'You already own this book.')
            ->assertJsonPath('meta.locale', 'en');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_api_order_allowed_when_access_is_premium_only(): void
    {
        // X5: Premium access is NOT ownership — a Premium user may buy
        // the book separately for permanent ownership after Premium ends.
        Subscription::create([
            'user_id' => $this->reader->id, 'plan_id' => $this->plan->id,
            'status' => 'active', 'starts_at' => now(), 'ends_at' => now()->addDays(30),
        ]);
        $this->asReader();

        $this->postJson('/api/v1/orders', [
            'type' => 'book', 'book_id' => $this->prophet->id,
        ])->assertCreated()->assertJsonPath('data.total_bdt', 96);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_api_order_allowed_when_book_not_owned(): void
    {
        $this->asReader();

        $this->postJson('/api/v1/orders', [
            'type' => 'book', 'book_id' => $this->gitanjali->id,
        ])->assertCreated()->assertJsonPath('data.type', 'book');

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_order_store_prices_server_side_and_trxid_flow(): void
    {
        $this->asReader();

        // Client-sent price is ignored: total is the DB sale price ৳96.
        $order = $this->postJson('/api/v1/orders', [
            'type' => 'book', 'book_id' => $this->prophet->id, 'total_bdt' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.total_bdt', 96)
            ->assertJsonPath('data.type', 'book');

        $orderId = $order->json('data.id');
        $this->assertSame('pending_payment', Order::find($orderId)->status);

        $this->postJson("/api/v1/orders/{$orderId}/trxid", ['method' => 'bkash', 'trx_id' => 'ABC1234567'])
            ->assertOk()->assertJsonPath('data.payment_status', 'awaiting_verification');

        $this->assertSame('awaiting_verification', Order::find($orderId)->status);
    }

    public function test_order_with_coupon_uses_sale_price_stack(): void
    {
        \App\Models\Coupon::create([
            'code' => 'EID26', 'percent' => 20, 'applies_to' => 'all', 'is_active' => true,
            'max_uses' => null, 'max_uses_per_user' => 1, 'used_count' => 0,
        ]);
        $this->asReader();

        // K150: 96 − 19 = 77.
        $this->postJson('/api/v1/orders', [
            'type' => 'book', 'book_id' => $this->prophet->id, 'coupon_code' => 'EID26',
        ])->assertCreated()->assertJsonPath('data.total_bdt', 77);
    }

    public function test_trxid_on_another_users_order_is_forbidden(): void
    {
        $other = \App\Models\User::factory()->create();
        $order = Order::create([
            'order_no' => 'LV-2026-1041', 'user_id' => $other->id, 'type' => 'book',
            'status' => 'pending_payment', 'subtotal_bdt' => 96, 'coupon_amt_bdt' => 0,
            'wallet_used_bdt' => 0, 'total_bdt' => 96,
        ]);
        $this->asReader();

        $this->postJson("/api/v1/orders/{$order->id}/trxid", ['method' => 'bkash', 'trx_id' => 'ABC1234567'])
            ->assertForbidden();
    }

    public function test_premium_show_lists_domestic_and_intl_plans(): void
    {
        $this->asReader();
        $this->getJson('/api/v1/premium')->assertOk()
            ->assertJsonPath('data.plan.price_bdt', 99)
            ->assertJsonPath('data.international_plan.price_bdt', 199)
            ->assertJsonPath('data.has_premium', false);
    }

    public function test_trial_starts_once(): void
    {
        $this->asReader();
        $this->postJson('/api/v1/premium/trial')->assertCreated()
            ->assertJsonPath('data.status', 'trialing');
        $this->assertNotNull($this->reader->fresh()->trial_used_at);

        // Second trial on the same account fails.
        $this->postJson('/api/v1/premium/trial')->assertStatus(422);
    }

    public function test_wallet_shows_balance_and_referral_credit(): void
    {
        $this->reader->update(['wallet_balance_bdt' => 226, 'points_balance' => 340]);
        app(\App\Services\BillingService::class)->referralCredit($this->reader->fresh(), 50, 'Test referral');
        $this->asReader();

        $this->getJson('/api/v1/wallet')->assertOk()
            ->assertJsonPath('data.balance_bdt', 276)
            ->assertJsonPath('data.points_balance', 340)
            ->assertJsonPath('data.referral_credit_bdt', 50);
    }
}
