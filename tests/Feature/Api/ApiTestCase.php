<?php

namespace Tests\Feature\Api;

use App\Models\Book;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $reader;

    protected Book $prophet;

    protected Book $gitanjali;

    protected SubscriptionPlan $plan;

    protected SubscriptionPlan $intlPlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([CategorySeeder::class, AuthorSeeder::class, BookSeeder::class]);

        foreach (['reader', 'author', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->reader = User::factory()->create(['phone' => '01700000002']);
        $this->prophet = Book::where('slug', 'the-prophet')->firstOrFail();
        $this->gitanjali = Book::where('slug', 'gitanjali')->firstOrFail();

        $this->plan = SubscriptionPlan::create([
            'slug' => 'premium-monthly', 'name' => 'LearnVow Premium', 'price_bdt' => 99,
            'duration_days' => 30, 'trial_days' => 7, 'is_active' => true,
        ]);
        $this->intlPlan = SubscriptionPlan::create([
            'slug' => 'premium-monthly-intl', 'name' => 'Premium International', 'price_bdt' => 199,
            'duration_days' => 30, 'trial_days' => 7, 'is_active' => true,
        ]);
    }

    protected function asReader(?User $user = null): User
    {
        $user ??= $this->reader;
        Sanctum::actingAs($user, ['app-access']);

        return $user;
    }

    protected function apiTokenFor(User $user): string
    {
        return $user->createToken('Test — Flutter', ['app-access'], now()->addDays(365))->plainTextToken;
    }
}
