<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cookie;
use Livewire\Livewire;
use Tests\TestCase;

class LanguageSwitcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoUserSeeder::class);
    }

    public function test_switching_language_sets_cookie_and_redirects_to_same_page(): void
    {
        $this->withSession(['_previous' => ['url' => url('/bn/books')]]);

        $response = Livewire::test('language-switcher')
            ->call('switchTo', 'en');

        $response->assertRedirect('/en/books');

        // The cookie is queued on the response (Livewire's test response
        // does not expose it via assertCookie, so check the jar directly).
        $this->assertSame('en', Cookie::queued('lv_locale')?->getValue());
    }

    public function test_switching_language_updates_logged_in_users_locale(): void
    {
        $user = User::where('phone', '01700000002')->first();
        $this->actingAs($user);

        Livewire::test('language-switcher')
            ->call('switchTo', 'en')
            ->assertRedirect('/en');

        $this->assertSame('en', $user->fresh()->locale);
    }

    public function test_invalid_locale_is_ignored(): void
    {
        $response = Livewire::test('language-switcher')
            ->call('switchTo', 'fr');

        $response->assertNoRedirect();
    }
}
