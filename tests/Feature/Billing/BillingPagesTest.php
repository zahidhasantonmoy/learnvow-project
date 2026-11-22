<?php

namespace Tests\Feature\Billing;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Billing pages render in bn + en (spec §13: BN + EN, no layout break). */
class BillingPagesTest extends TestCase
{
    use RefreshDatabase;

    private User $reader;
    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $author = Author::create(['name' => 'Test Author', 'slug' => 'test-author', 'status' => 'approved']);
        $category = Category::create(['name' => 'উপন্যাস', 'name_en' => 'Novel', 'slug' => 'novel']);
        $book = Book::create([
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
        $billing = app(BillingService::class);
        $priced = $billing->priceOrder(['user' => $this->reader, 'type' => 'book', 'book_id' => $book->id, 'coupon_code' => 'EID26']);
        $this->order = $billing->createOrder($this->reader, $priced);
    }

    #[DataProvider('localeProvider')]
    public function test_billing_pages_render(string $locale): void
    {
        $this->actingAs($this->reader);

        $urls = [
            "/{$locale}/premium",
            "/{$locale}/subscription",
            "/{$locale}/checkout/the-prophet",
            "/{$locale}/checkout/premium",
            "/{$locale}/payment",
            "/{$locale}/payment/success/{$this->order->id}",
            "/{$locale}/payment/failed/{$this->order->id}",
            "/{$locale}/payment/fix-trxid/{$this->order->id}",
            "/{$locale}/wallet",
            "/{$locale}/wallet/saved",
            "/{$locale}/orders",
            "/{$locale}/orders/{$this->order->id}",
            "/{$locale}/points",
            "/{$locale}/referral",
            "/{$locale}/gift/buy/the-prophet",
            "/{$locale}/gift/redeem",
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk("GET {$url} failed");
        }
    }

    /** Spec §8/§13: reader/checkout/wallet/admin pages must be noindex. */
    #[DataProvider('localeProvider')]
    public function test_billing_pages_emit_noindex(string $locale): void
    {
        $this->actingAs($this->reader);

        $urls = [
            "/{$locale}/premium",
            "/{$locale}/subscription",
            "/{$locale}/checkout/the-prophet",
            "/{$locale}/checkout/premium",
            "/{$locale}/payment",
            "/{$locale}/payment/success/{$this->order->id}",
            "/{$locale}/payment/failed/{$this->order->id}",
            "/{$locale}/payment/fix-trxid/{$this->order->id}",
            "/{$locale}/wallet",
            "/{$locale}/wallet/saved",
            "/{$locale}/orders",
            "/{$locale}/orders/{$this->order->id}",
            "/{$locale}/points",
            "/{$locale}/referral",
            "/{$locale}/gift/buy/the-prophet",
            "/{$locale}/gift/redeem",
        ];

        foreach ($urls as $url) {
            $this->get($url)->assertOk("GET {$url} failed")
                ->assertSee('<meta name="robots" content="noindex, nofollow">', false, "noindex missing on {$url}");
        }
    }

    public function test_gift_redeem_is_visible_to_guests(): void
    {
        $this->get('/bn/gift/redeem')->assertOk();
        $this->get('/en/gift/redeem')->assertOk();
    }

    public function test_billing_pages_require_login(): void
    {
        $this->get('/bn/premium')->assertRedirect();
        $this->get('/bn/wallet')->assertRedirect();
        $this->get('/bn/orders')->assertRedirect();
    }

    public function test_other_users_cannot_view_my_order(): void
    {
        $other = User::factory()->create(['phone' => '01700000009']);
        $this->actingAs($other);
        $this->get("/bn/orders/{$this->order->id}")->assertForbidden();
    }

    public static function localeProvider(): array
    {
        return [['bn'], ['en']];
    }
}
