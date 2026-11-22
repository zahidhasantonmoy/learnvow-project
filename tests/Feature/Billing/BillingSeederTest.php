<?php

namespace Tests\Feature\Billing;

use Database\Seeders\AuthorSeeder;
use Database\Seeders\BillingSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** BillingSeeder frozen data (spec §7) — seeded exactly, nothing invented. */
class BillingSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            CategorySeeder::class,
            AuthorSeeder::class,
            BookSeeder::class,
            DemoUserSeeder::class,
            BillingSeeder::class,
        ]);
    }

    public function test_frozen_billing_seed_data(): void
    {
        $this->assertDatabaseHas('subscription_plans', [
            'slug' => 'premium-monthly', 'price_bdt' => 99, 'duration_days' => 30, 'trial_days' => 7,
        ]);
        $this->assertDatabaseHas('coupons', [
            'code' => 'EID26', 'percent' => 20, 'applies_to' => 'all', 'is_active' => true,
        ]);
        $this->assertDatabaseHas('gift_codes', ['code' => 'GIFT-7K2M9P', 'status' => 'active', 'claimed_by' => null]);

        $reader = \App\Models\User::where('phone', '01700000002')->firstOrFail();
        $this->assertSame(226, $reader->wallet_balance_bdt);
        $this->assertSame(340, $reader->points_balance);
        $this->assertDatabaseHas('wallet_transactions', [
            'user_id' => $reader->id, 'type' => 'topup', 'amount_bdt' => 226, 'balance_after_bdt' => 226,
        ]);
        $this->assertDatabaseHas('point_transactions', [
            'user_id' => $reader->id, 'type' => 'earn_purchase', 'points' => 340, 'balance_after' => 340,
        ]);

        // Demo payment numbers are NOT seeded (spec A16).
        $this->assertSame('', \DB::table('settings')->where('key', 'payment.bkash_number')->value('value'));
        $this->assertSame('', \DB::table('settings')->where('key', 'payment.nagad_number')->value('value'));

        $this->assertDatabaseHas('orders', ['order_no' => 'LV-2026-1041', 'status' => 'paid', 'total_bdt' => 150]);
        $this->assertDatabaseHas('orders', ['order_no' => 'LV-2026-1042', 'status' => 'paid', 'total_bdt' => 99]);
        $this->assertDatabaseHas('orders', ['order_no' => 'LV-2026-1043', 'status' => 'awaiting_verification', 'subtotal_bdt' => 96, 'coupon_amt_bdt' => 19, 'total_bdt' => 77]);
        $this->assertDatabaseHas('orders', ['order_no' => 'LV-2026-1044', 'status' => 'duplicate_suspect']);

        $this->assertDatabaseHas('payments', ['trx_id_normalized' => '9HX7K2LMNP', 'status' => 'verified']);
        $this->assertDatabaseHas('payments', ['trx_id_normalized' => '9HW3PZ77AB', 'status' => 'awaiting_verification']);
        $this->assertDatabaseHas('payments', ['trx_id_normalized' => '9HX7K2LMNP', 'status' => 'duplicate_suspect']);
        $this->assertDatabaseHas('fraud_alerts', ['trx_id_normalized' => '9HX7K2LMNP', 'status' => 'open']);

        $pride = \App\Models\Book::where('slug', 'pride-and-prejudice')->firstOrFail();
        $this->assertDatabaseHas('user_books', ['user_id' => $reader->id, 'book_id' => $pride->id, 'source' => 'purchase']);

        $subscription = \App\Models\Subscription::where('user_id', $reader->id)->where('status', 'active')->firstOrFail();
        $this->assertSame('2026-11-15', $subscription->starts_at->format('Y-m-d'));
        $this->assertSame('2026-12-15', $subscription->ends_at->format('Y-m-d'));
    }
}
