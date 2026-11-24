<?php

namespace Tests\Feature\Subscriptions;

use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** International web pages (spec §3.1, §14.6): ৳199 plan, manual TrxID only. */
class InternationalWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([CategorySeeder::class, AuthorSeeder::class, BookSeeder::class]);

        SubscriptionPlan::create([
            'slug' => 'premium-monthly', 'name' => 'LearnVow Premium', 'price_bdt' => 99,
            'duration_days' => 30, 'trial_days' => 7, 'is_active' => true,
        ]);
        SubscriptionPlan::create([
            'slug' => 'premium-monthly-intl', 'name' => 'Premium International', 'price_bdt' => 199,
            'duration_days' => 30, 'trial_days' => 7, 'is_active' => true,
        ]);
    }

    public function test_international_premium_page_shows_199_and_manual_trxid_note(): void
    {
        $reader = User::factory()->create();
        $this->actingAs($reader);

        $this->get('/bn/premium/international')->assertOk()
            ->assertSee('199')
            ->assertSee('ম্যানুয়াল TrxID');
    }

    public function test_international_checkout_page_renders(): void
    {
        $reader = User::factory()->create();
        $this->actingAs($reader);

        $this->get('/bn/checkout/premium/international')->assertOk();
    }

    public function test_international_pages_require_login(): void
    {
        $this->get('/bn/premium/international')->assertRedirect('/bn/login');
    }
}
