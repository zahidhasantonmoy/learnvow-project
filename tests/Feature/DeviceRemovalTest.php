<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Role-audit Medium-1 (c): login-free OTP self-service device removal.
 * Reuses the SEC-01 OTP system with the new `device_removal` purpose.
 */
class DeviceRemovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoUserSeeder::class);
    }

    private function fillDevices(User $user): array
    {
        $ids = [];
        foreach (['Phone', 'Tablet', 'Laptop'] as $i => $name) {
            $ids[] = Device::create([
                'user_id' => $user->id,
                'device_name' => $name,
                'device_type' => 'app',
                'platform' => null,
                'last_ip' => '10.0.0.'.($i + 1),
                'user_agent' => 'TestAgent/'.$name,
                'last_active_at' => now(),
                'api_token_id' => null,
            ])->id;
        }

        return $ids;
    }

    public function test_remove_device_page_renders_in_both_locales(): void
    {
        $this->get('/bn/remove-device')->assertOk()->assertSee('ডিভাইস সরান');
        $this->get('/en/remove-device')->assertOk()->assertSee('Remove a device');
    }

    public function test_otp_device_removal_end_to_end_then_login_succeeds(): void
    {
        $user = User::where('phone', '01700000002')->first();
        $deviceIds = $this->fillDevices($user);

        // Locked out before removal: correct password, device message.
        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors(['phone']);
        $this->assertGuest();

        // Step 1: request the OTP (login-free).
        $removal = Livewire::test('auth.device-removal-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSee(__('auth.otp_sent'));

        // Step 2: verify with a freshly issued code for this purpose,
        // then the device list appears.
        $code = app(AuthService::class)->requestOtp('01700000002', AuthService::OTP_PURPOSE_DEVICE);

        $removal
            ->set('code', $code)
            ->call('verify')
            ->assertHasNoErrors()
            ->assertSee('Phone')
            ->assertSee('Tablet')
            ->assertSee('Laptop');

        // Step 3: remove one device.
        $removal
            ->call('removeDevice', $deviceIds[0])
            ->assertSee(__('auth.device_removed'));

        $this->assertSame(2, Device::where('user_id', $user->id)->count());
        $this->assertDatabaseMissing('devices', ['id' => $deviceIds[0]]);

        // Login now succeeds from a new device.
        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect('/bn');

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_otp_is_rejected_and_no_devices_are_shown(): void
    {
        $user = User::where('phone', '01700000002')->first();
        $this->fillDevices($user);

        app(AuthService::class)->requestOtp('01700000002', AuthService::OTP_PURPOSE_DEVICE);

        Livewire::test('auth.device-removal-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->call('send')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors(['code'])
            // Device names must not leak before a correct OTP.
            ->assertDontSee('Tablet')
            ->assertDontSee('Laptop');

        $this->assertSame(3, Device::where('user_id', $user->id)->count());
    }

    public function test_device_removal_otp_is_single_use(): void
    {
        $auth = app(AuthService::class);
        $code = $auth->requestOtp('01700000002', AuthService::OTP_PURPOSE_DEVICE);

        $this->assertTrue($auth->verifyOtp('01700000002', $code, AuthService::OTP_PURPOSE_DEVICE, false));

        // Reuse through the form is rejected.
        Livewire::test('auth.device-removal-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', $code)
            ->call('verify')
            ->assertHasErrors(['code']);
    }

    public function test_otp_purpose_is_separated_from_phone_verification(): void
    {
        // A code issued for phone verification must not open device removal.
        $code = app(AuthService::class)->requestOtp('01700000002', AuthService::OTP_PURPOSE_VERIFY);

        Livewire::test('auth.device-removal-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', $code)
            ->call('verify')
            ->assertHasErrors(['code']);
    }

    public function test_device_removal_otp_does_not_mark_phone_verified(): void
    {
        $user = User::where('phone', '01700000002')->first();
        $this->assertNull($user->phone_verified_at);

        $code = app(AuthService::class)->requestOtp('01700000002', AuthService::OTP_PURPOSE_DEVICE);

        Livewire::test('auth.device-removal-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', $code)
            ->call('verify')
            ->assertHasNoErrors();

        $this->assertNull(User::where('phone', '01700000002')->value('phone_verified_at'));
    }

    public function test_otp_request_for_unknown_phone_gives_the_same_reply(): void
    {
        // No enumeration: an unknown phone gets the same "OTP sent" reply,
        // and no code exists to verify with.
        Livewire::test('auth.device-removal-form', ['locale' => 'bn'])
            ->set('phone', '01999999999')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSee(__('auth.otp_sent'))
            ->set('code', '123456')
            ->call('verify')
            ->assertHasErrors(['code']);
    }

    public function test_otp_send_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            Livewire::test('auth.device-removal-form', ['locale' => 'bn'])
                ->set('phone', '01700000002')
                ->call('send')
                ->assertHasNoErrors();
        }

        Livewire::test('auth.device-removal-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->call('send')
            ->assertHasErrors(['phone']);
    }

    public function test_otp_verify_is_rate_limited(): void
    {
        app(AuthService::class)->requestOtp('01700000002', AuthService::OTP_PURPOSE_DEVICE);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test('auth.device-removal-form', ['locale' => 'bn'])
                ->set('otpSent', true)
                ->set('phone', '01700000002')
                ->set('code', '000000')
                ->call('verify')
                ->assertHasErrors(['code']);
        }

        // The 6th verify inside a minute is throttled, not just invalid.
        // (Livewire test requests run in the default app locale, so the
        // throttled message renders in English here.)
        Livewire::test('auth.device-removal-form', ['locale' => 'bn'])
            ->set('otpSent', true)
            ->set('phone', '01700000002')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors(['code'])
            ->assertSee('Too many attempts');
    }
}
