<?php

namespace Tests\Feature;

use App\Filament\Auth\AdminLogin;
use App\Models\Device;
use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Role-audit Medium-1 (a) + (b): the device limit must be named, not
 * hidden behind "wrong password", on the web login — and /admin login
 * must enforce the same limit instead of bypassing it.
 */
class DeviceLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoUserSeeder::class);
    }

    /** Fill a user's 3 device slots with distinct fingerprints. */
    private function fillDevices(User $user): void
    {
        foreach (['Phone', 'Tablet', 'Laptop'] as $i => $name) {
            Device::create([
                'user_id' => $user->id,
                'device_name' => $name,
                'device_type' => 'app',
                'platform' => null,
                'last_ip' => '10.0.0.'.($i + 1),
                'user_agent' => 'TestAgent/'.$name,
                'last_active_at' => now(),
                'api_token_id' => null,
            ]);
        }
    }

    public function test_web_login_at_device_limit_shows_device_message_not_login_failed(): void
    {
        $user = User::where('phone', '01700000002')->first();
        $this->fillDevices($user);

        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['phone'])
            ->assertSee(__('auth.device_limit'))
            ->assertDontSee(__('auth.login_failed'));

        $this->assertGuest();
        $this->assertSame(3, Device::where('user_id', $user->id)->count());
    }

    public function test_web_login_at_device_limit_links_to_device_removal(): void
    {
        $user = User::where('phone', '01700000002')->first();
        $this->fillDevices($user);

        Livewire::test('auth.login-form', ['locale' => 'en'])
            ->set('phone', '01700000002')
            ->set('password', 'password')
            ->call('login')
            ->assertSee(__('auth.remove_device'))
            ->assertSee(route('devices.remove', ['locale' => 'en']));
    }

    public function test_wrong_password_still_shows_generic_login_failed(): void
    {
        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors(['phone'])
            ->assertSee(__('auth.login_failed'))
            ->assertDontSee(__('auth.device_limit'));

        $this->assertGuest();
    }

    public function test_admin_login_at_device_limit_is_blocked_with_device_message(): void
    {
        $admin = User::where('phone', '01700000001')->first();
        $this->fillDevices($admin);

        Livewire::test(AdminLogin::class)
            ->set('data.phone', '01700000001')
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['data.phone']);

        $this->assertGuest();
        $this->assertSame(3, Device::where('user_id', $admin->id)->count());
    }

    public function test_admin_login_registers_a_device_on_success(): void
    {
        $admin = User::where('phone', '01700000001')->first();

        Livewire::test(AdminLogin::class)
            ->set('data.phone', '01700000001')
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
        $this->assertSame(1, Device::where('user_id', $admin->id)->count());
    }

    public function test_reader_still_cannot_log_in_to_the_admin_panel(): void
    {
        Livewire::test(AdminLogin::class)
            ->set('data.phone', '01700000002')
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['data.phone']);

        $this->assertGuest();
        $this->assertSame(0, Device::where('user_id', User::where('phone', '01700000002')->first()->id)->count());
    }

    public function test_super_admin_login_at_device_limit_is_blocked(): void
    {
        $superAdmin = User::where('phone', '01700000000')->first();
        $this->fillDevices($superAdmin);

        Livewire::test(AdminLogin::class)
            ->set('data.phone', '01700000000')
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['data.phone']);

        $this->assertGuest();
    }
}
