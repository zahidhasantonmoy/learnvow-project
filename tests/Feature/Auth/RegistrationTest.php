<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        $this->seed(DemoUserSeeder::class);
    }

    public function test_register_page_renders(): void
    {
        $this->get('/bn/register')->assertOk();
        $this->get('/en/register')->assertOk();
    }

    public function test_new_user_registers_and_gets_reader_role(): void
    {
        Livewire::test('auth.register-form', ['locale' => 'bn'])
            ->set('name', 'New Reader')
            ->set('phone', '01812345678')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertRedirect(route('otp', ['locale' => 'bn']));

        $user = User::where('phone', '01812345678')->first();

        $this->assertNotNull($user);
        $this->assertSame('bn', $user->locale);
        $this->assertSame('active', $user->status);
        $this->assertTrue($user->hasRole('reader'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_uses_locale_from_url_prefix(): void
    {
        Livewire::test('auth.register-form', ['locale' => 'en'])
            ->set('name', 'English Reader')
            ->set('phone', '01812345679')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register');

        $this->assertSame('en', User::where('phone', '01812345679')->value('locale'));
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        Livewire::test('auth.register-form', ['locale' => 'bn'])
            ->set('name', 'Copy Cat')
            ->set('phone', '01700000002') // demo reader already owns this phone
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertHasErrors(['phone']);

        $this->assertSame(1, User::where('phone', '01700000002')->count());
    }

    public function test_short_password_is_rejected(): void
    {
        Livewire::test('auth.register-form', ['locale' => 'bn'])
            ->set('name', 'Short Pass')
            ->set('phone', '01812345680')
            ->set('password', 'short')
            ->set('password_confirmation', 'short')
            ->call('register')
            ->assertHasErrors(['password']);

        $this->assertNull(User::where('phone', '01812345680')->first());
    }

    public function test_password_confirmation_must_match(): void
    {
        Livewire::test('auth.register-form', ['locale' => 'bn'])
            ->set('name', 'Mismatch')
            ->set('phone', '01812345681')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'different')
            ->call('register')
            ->assertHasErrors(['password']);

        $this->assertNull(User::where('phone', '01812345681')->first());
    }

    public function test_invalid_phone_format_is_rejected(): void
    {
        Livewire::test('auth.register-form', ['locale' => 'bn'])
            ->set('name', 'Bad Phone')
            ->set('phone', '12345')
            ->set('password', 'secret123')
            ->set('password_confirmation', 'secret123')
            ->call('register')
            ->assertHasErrors(['phone']);
    }
}
