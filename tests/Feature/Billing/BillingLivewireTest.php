<?php

namespace Tests\Feature\Billing;

use App\Livewire\CheckoutForm;
use App\Livewire\SubscriptionManager;
use App\Livewire\TrxIdForm;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/** Livewire checkout / TrxID-fix / trial flows (spec §10). */
class BillingLivewireTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;
    private Book $prophet;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'reader', 'guard_name' => 'web']);
        $author = Author::create(['name' => 'Test Author', 'slug' => 'test-author', 'status' => 'approved']);
        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel']);
        $this->prophet = Book::create([
            'author_id' => $author->id, 'category_id' => $category->id,
            'title' => 'The Prophet', 'slug' => 'the-prophet', 'description' => 'Prophet.',
            'language' => 'en', 'price_bdt' => 96, 'list_price_bdt' => 120, 'status' => 'published',
        ]);
        SubscriptionPlan::create([
            'slug' => 'premium-monthly', 'name' => 'LearnVow Premium', 'price_bdt' => 99,
            'duration_days' => 30, 'trial_days' => 7, 'is_active' => true,
        ]);
        Coupon::create(['code' => 'EID26', 'percent' => 20, 'applies_to' => 'all', 'is_active' => true]);
        $this->reader = User::factory()->create(['phone' => '01700000002']);
    }

    public function test_checkout_form_submits_trxid_for_77(): void
    {
        $this->actingAs($this->reader);

        Livewire::test(CheckoutForm::class, ['type' => 'book', 'bookId' => $this->prophet->id])
            ->set('couponCode', 'EID26')
            ->set('method', 'bkash')
            ->set('trxId', '9HX7K2LMNP')
            ->call('submit')
            ->assertRedirect();

        $order = \App\Models\Order::where('user_id', $this->reader->id)->firstOrFail();
        $this->assertSame(77, $order->total_bdt);
        $this->assertSame('awaiting_verification', $order->status);
        $this->assertDatabaseHas('payments', ['order_id' => $order->id, 'trx_id_normalized' => '9HX7K2LMNP']);
    }

    public function test_checkout_form_with_invalid_trxid_creates_no_order(): void
    {
        // AUD3-02: a bad TrxID used to leave an orphan pending_payment
        // order behind. The format is now checked before createOrder.
        $this->actingAs($this->reader);

        Livewire::test(CheckoutForm::class, ['type' => 'book', 'bookId' => $this->prophet->id])
            ->set('method', 'bkash')
            ->set('trxId', 'BAD!')
            ->call('submit')
            ->assertSet('error', __('billing.trxid_invalid'));

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_wallet_topup_with_invalid_trxid_creates_no_order(): void
    {
        // AUD3-02, wallet path: same orphan-order bug as checkout.
        $this->actingAs($this->reader);

        Livewire::test(\App\Livewire\WalletTopup::class)
            ->set('amount', 100)
            ->set('method', 'bkash')
            ->set('trxId', '')
            ->call('submit')
            ->assertSet('error', __('billing.trxid_invalid'));

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_form_rejects_invalid_coupon_live(): void
    {
        $this->actingAs($this->reader);

        Livewire::test(CheckoutForm::class, ['type' => 'book', 'bookId' => $this->prophet->id])
            ->set('couponCode', 'NOPE99')
            ->call('applyCoupon')
            ->assertSet('couponMessage', __('billing.coupon_invalid'));
    }

    public function test_trxid_form_fixes_wrong_trxid(): void
    {
        $billing = app(BillingService::class);
        $priced = $billing->priceOrder(['user' => $this->reader, 'type' => 'book', 'book_id' => $this->prophet->id]);
        $order = $billing->createOrder($this->reader, $priced);
        $billing->submitTrxId($order, 'bkash', '9HW3PZ77AB');

        $this->actingAs($this->reader);

        Livewire::test(TrxIdForm::class, ['order' => $order])
            ->set('trxId', '9HW3PZ77AC')
            ->call('submit')
            ->assertRedirect();

        $payment = $order->payments()->firstOrFail();
        $this->assertSame('9HW3PZ77AC', $payment->fresh()->trx_id_normalized);
        $this->assertSame(1, $payment->fresh()->fix_count);
    }

    public function test_subscription_manager_starts_trial_once(): void
    {
        $this->actingAs($this->reader);

        Livewire::test(SubscriptionManager::class)
            ->call('startTrial')
            ->assertSet('notice', __('billing.trial_started'));

        $this->assertTrue(app(BillingService::class)->hasActivePremium($this->reader->fresh()));
    }
}
