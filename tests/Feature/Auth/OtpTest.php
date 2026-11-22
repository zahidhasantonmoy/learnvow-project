<?php

namespace Tests\Feature\Auth;

use App\Models\User;
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

    public function test_any_six_digit_code_is_accepted_in_dev_and_verifies_phone(): void
    {
        // Mocked OTP (A3): any 6 digits work in dev.
        Livewire::test('auth.otp-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', '482916')
            ->call('verify')
            ->assertRedirect('/bn');

        $this->assertNotNull(User::where('phone', '01700000002')->value('phone_verified_at'));
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

    public function test_forgot_password_sends_otp_and_redirects_to_reset(): void
    {
        Livewire::test('auth.forgot-password-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->call('send')
            ->assertRedirect(route('password.reset', ['locale' => 'bn']));
    }

    public function test_reset_password_with_mocked_otp_changes_password(): void
    {
        Livewire::test('auth.reset-password-form', ['locale' => 'bn'])
            ->set('phone', '01700000002')
            ->set('code', '654321')
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
}
