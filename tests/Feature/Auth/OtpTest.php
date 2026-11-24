<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\AuthService;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoUserSeeder::class);
    }

    public function test_otp_page_renders(): void
    {
        $this->get('/bn/otp')->assertOk();
        $this->get('/en/otp')->assertOk();
    }

    public function test_forgot_and_reset_pages_render(): void
    {
        $this->get('/bn/forgot-password')->assertOk();
        $this->get('/en/forgot-password')->assertOk();
        $this->get('/bn/reset-password')->assertOk();
        $this->get('/en/reset-password')->assertOk();
    }

    public function test_correct_otp_verifies_phone(): void
    {
        // SEC-01: only the issued code verifies — an arbitrary code must not.
        $code = app(AuthService::class)->requestOtp('01700000002');

        Livewire::test('auth.otp-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', $code)
            ->call('verify')
            ->assertRedirect('/bn');

        $this->assertNotNull(User::where('phone', '01700000002')->value('phone_verified_at'));
    }

    public function test_wrong_otp_code_fails(): void
    {
        app(AuthService::class)->requestOtp('01700000002');

        Livewire::test('auth.otp-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors(['code']);

        $this->assertNull(User::where('phone', '01700000002')->value('phone_verified_at'));
    }

    public function test_short_code_is_rejected(): void
    {
        Livewire::test('auth.otp-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', '123')
            ->call('verify')
            ->assertHasErrors(['code']);

        $this->assertNull(User::where('phone', '01700000002')->value('phone_verified_at'));
    }

    public function test_otp_for_unknown_phone_fails(): void
    {
        Livewire::test('auth.otp-form', ['locale' => 'bn'])
            ->set('phone', '01999999999')
            ->set('code', '123456')
            ->call('verify')
            ->assertHasErrors(['code']);
    }

    public function test_expired_otp_fails(): void
    {
        $code = app(AuthService::class)->requestOtp('01700000002');

        $this->travel(11)->minutes();

        Livewire::test('auth.otp-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', $code)
            ->call('verify')
            ->assertHasErrors(['code']);

        $this->assertNull(User::where('phone', '01700000002')->value('phone_verified_at'));
    }

    public function test_otp_cannot_be_reused_after_success(): void
    {
        $auth = app(AuthService::class);
        $code = $auth->requestOtp('01700000002');

        $this->assertTrue($auth->verifyOtp('01700000002', $code));
        // Single-use: the same code fails the second time.
        $this->assertFalse($auth->verifyOtp('01700000002', $code));
    }

    public function test_otp_blocked_after_five_failed_attempts(): void
    {
        $auth = app(AuthService::class);
        $code = $auth->requestOtp('01700000002');

        for ($i = 0; $i < 5; $i++) {
            $this->assertFalse($auth->verifyOtp('01700000002', '000000'));
        }

        // 6th attempt: even the correct code is now dead.
        $this->assertFalse($auth->verifyOtp('01700000002', $code));

        // A freshly issued code works again.
        $fresh = $auth->requestOtp('01700000002');
        $this->assertTrue($auth->verifyOtp('01700000002', $fresh));
    }

    public function test_verify_purpose_code_cannot_reset_password(): void
    {
        $auth = app(AuthService::class);
        $code = $auth->requestOtp('01700000002', AuthService::OTP_PURPOSE_VERIFY);

        $this->assertFalse($auth->resetPassword('01700000002', $code, 'newsecret1'));

        $user = User::where('phone', '01700000002')->first();
        $this->assertFalse(Hash::check('newsecret1', $user->password));
    }

    public function test_forgot_password_sends_otp_and_redirects_to_reset(): void
    {
        Livewire::test('auth.forgot-password-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->call('send')
            ->assertRedirect(route('password.reset', ['locale' => 'bn']));
    }

    public function test_reset_password_with_correct_otp_changes_password(): void
    {
        $code = app(AuthService::class)->requestOtp('01700000002', AuthService::OTP_PURPOSE_RESET);

        Livewire::test('auth.reset-password-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', $code)
            ->set('password', 'newsecret1')
            ->set('password_confirmation', 'newsecret1')
            ->call('resetPassword')
            ->assertRedirect(route('login', ['locale' => 'bn']));

        $user = User::where('phone', '01700000002')->first();

        $this->assertTrue(Hash::check('newsecret1', $user->password));

        // And the new password actually logs in.
        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'newsecret1')
            ->call('login')
            ->assertRedirect('/bn');
    }

    public function test_reset_password_with_wrong_code_fails(): void
    {
        // SEC-01: an arbitrary 6-digit code must NOT reset the password.
        app(AuthService::class)->requestOtp('01700000002', AuthService::OTP_PURPOSE_RESET);

        Livewire::test('auth.reset-password-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', '000000')
            ->set('password', 'newsecret1')
            ->set('password_confirmation', 'newsecret1')
            ->call('resetPassword')
            ->assertHasErrors(['code']);

        $user = User::where('phone', '01700000002')->first();
        $this->assertFalse(Hash::check('newsecret1', $user->password));
    }

    public function test_reset_password_with_no_otp_requested_fails(): void
    {
        // No OTP was ever issued for this phone — any code must fail.
        Livewire::test('auth.reset-password-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', '123456')
            ->set('password', 'newsecret1')
            ->set('password_confirmation', 'newsecret1')
            ->call('resetPassword')
            ->assertHasErrors(['code']);

        $user = User::where('phone', '01700000002')->first();
        $this->assertFalse(Hash::check('newsecret1', $user->password));
    }

    public function test_reset_password_with_short_code_fails(): void
    {
        Livewire::test('auth.reset-password-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', '12')
            ->set('password', 'newsecret1')
            ->set('password_confirmation', 'newsecret1')
            ->call('resetPassword')
            ->assertHasErrors(['code']);
    }

    // --- N1 (re-QA 2026-11-24): registration → OTP → login flow ---

    public function test_registration_reaches_otp_page_as_guest(): void
    {
        // (a) Registration redirects to the OTP page and does NOT log in.
        Livewire::test('auth.register-form', ['locale' => 'bn'])
            ->set('name', 'OTP Flow')
            ->set('phone', '01812345700')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertRedirect(route('otp', ['locale' => 'bn']));

        $this->assertGuest();
        $this->assertNull(User::where('phone', '01812345700')->value('phone_verified_at'));

        // The OTP page itself is reachable as a guest (it used to bounce
        // a freshly registered user home via `guest` middleware).
        $this->get('/bn/otp')->assertOk();
    }

    public function test_registration_then_correct_otp_verifies_and_logs_in(): void
    {
        // (b) Correct OTP completes verification AND logs the user in.
        Livewire::test('auth.register-form', ['locale' => 'bn'])
            ->set('name', 'OTP Flow')
            ->set('phone', '01812345701')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertRedirect(route('otp', ['locale' => 'bn']));

        $this->assertGuest();

        // The registration-issued code is hashed in the cache and not
        // retrievable; issue a fresh known code for the same phone, as
        // the resend action would.
        $code = app(AuthService::class)->requestOtp('01812345701');

        Livewire::test('auth.otp-form', ['locale' => 'bn'])
            ->set('phone', '01812345701')
            ->set('code', $code)
            ->call('verify')
            ->assertRedirect('/bn');

        $this->assertAuthenticatedAs(User::where('phone', '01812345701')->first());
        $this->assertNotNull(User::where('phone', '01812345701')->value('phone_verified_at'));
    }

    public function test_registration_then_wrong_otp_stays_guest_and_unverified(): void
    {
        // (c) Wrong OTP fails and the user stays a guest, unverified.
        Livewire::test('auth.register-form', ['locale' => 'bn'])
            ->set('name', 'OTP Flow')
            ->set('phone', '01812345702')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertRedirect(route('otp', ['locale' => 'bn']));

        app(AuthService::class)->requestOtp('01812345702');

        Livewire::test('auth.otp-form', ['locale' => 'bn'])
            ->set('phone', '01812345702')
            ->set('code', '000000')
            ->call('verify')
            ->assertHasErrors(['code']);

        $this->assertGuest();
        $this->assertNull(User::where('phone', '01812345702')->value('phone_verified_at'));
    }

    public function test_existing_demo_account_still_logs_in_with_password_only(): void
    {
        // (d) No global verification enforcement: an existing (even
        // unverified) demo account logs in normally with password only.
        $this->assertNull(User::where('phone', '01700000002')->value('phone_verified_at'));

        Livewire::test('auth.login-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect('/bn');

        $this->assertAuthenticatedAs(User::where('phone', '01700000002')->first());
    }
}
