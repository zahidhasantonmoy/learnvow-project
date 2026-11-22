<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoUserSeeder::class);
    }

    public function test_login_page_renders(): void
    {
        $this->get('/bn/login')->assertOk()->assertSee('লগইন');
        $this->get('/en/login')->assertOk()->assertSee('Login');
    }

    public function test_demo_reader_can_login_with_phone_and_password(): void
    {
        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect('/bn');

        $this->assertAuthenticatedAs(User::where('phone', '01700000002')->first());
    }

    public function test_all_four_demo_users_can_login(): void
    {
        foreach (['01700000000', '01700000001', '01700000002', '01700000003'] as $phone) {
            $this->post('/bn/logout');
            auth()->logout();

            Livewire::test('auth.login-form', ['locale' => 'bn'])
                ->set('phone', $phone)
                ->set('password', 'password')
                ->call('login')
                ->assertRedirect('/bn');

            $this->assertAuthenticated();
        }
    }

    public function test_login_fails_with_wrong_password(): void
    {
        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['phone']);

        $this->assertGuest();
    }

    public function test_login_fails_for_unknown_phone(): void
    {
        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01999999999')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['phone']);

        $this->assertGuest();
    }

    public function test_five_wrong_attempts_lock_login_for_a_minute(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Livewire::test('auth.login-form', ['locale' => 'bn'])
                ->set('phone', '01700000002')
                ->set('password', 'wrong-password')
                ->call('login')
                ->assertHasErrors(['phone']);
        }

        // The 6th attempt — even with the right password — is locked out.
        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['phone']);

        $this->assertGuest();
    }

    public function test_suspended_user_cannot_login(): void
    {
        User::where('phone', '01700000002')->update(['status' => 'suspended']);

        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['phone']);

        $this->assertGuest();
    }

    public function test_banned_user_cannot_login(): void
    {
        User::where('phone', '01700000002')->update(['status' => 'banned']);

        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['phone']);

        $this->assertGuest();
    }

    public function test_logout_ends_the_session(): void
    {
        $user = User::where('phone', '01700000002')->first();
        $this->actingAs($user);

        $this->post('/bn/logout')->assertRedirect('/bn');

        $this->assertGuest();
    }

    public function test_logout_requires_authentication(): void
    {
        $this->post('/bn/logout')->assertRedirect('/bn/login');
    }
}
