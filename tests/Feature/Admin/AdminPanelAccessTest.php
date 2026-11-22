<?php

namespace Tests\Feature\Admin;

use App\Filament\Auth\AdminLogin;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Builder C — /admin panel access (spec §3.4, A15): knowing the URL is
 * never enough. Guest is sent to login, Reader gets 403, Admin gets in
 * but is barred from roles/settings, Super Admin gets everywhere.
 */
class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['reader', 'author', 'admin', 'super-admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }

        $this->admin = User::factory()->create();
        AdminService::assignRole($this->admin, 'admin');

        $this->superAdmin = User::factory()->create();
        AdminService::assignRole($this->superAdmin, 'super-admin');

        $this->reader = User::factory()->create();
        AdminService::assignRole($this->reader, 'reader');
    }

    public function test_admin_can_log_in_with_phone_and_password(): void
    {
        $this->admin->update(['phone' => '01700000001']);

        Livewire::test(AdminLogin::class)
            ->set('data.phone', '01700000001')
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_reader_cannot_log_in_to_the_admin_panel(): void
    {
        $this->reader->update(['phone' => '01700000002']);

        Livewire::test(AdminLogin::class)
            ->set('data.phone', '01700000002')
            ->set('data.password', 'password')
            ->call('authenticate')
            ->assertHasErrors(['data.phone']);

        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_reader_gets_403_on_admin_dashboard(): void
    {
        $this->actingAs($this->reader)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_open_the_dashboard(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertOk();
    }

    public function test_super_admin_can_open_the_dashboard(): void
    {
        $this->actingAs($this->superAdmin)->get('/admin')->assertOk();
    }

    public function test_admin_can_open_each_phase1_resource_list(): void
    {
        foreach (['books', 'authors', 'users', 'categories', 'reviews'] as $resource) {
            $this->actingAs($this->admin)->get("/admin/{$resource}")->assertOk();
        }
    }

    public function test_admin_cannot_open_roles_or_settings(): void
    {
        $this->actingAs($this->admin)->get('/admin/roles')->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/settings')->assertForbidden();
    }

    public function test_super_admin_can_open_roles_and_settings(): void
    {
        $this->actingAs($this->superAdmin)->get('/admin/roles')->assertOk();
        $this->actingAs($this->superAdmin)->get('/admin/settings')->assertOk();
    }

    public function test_reader_cannot_open_any_resource_list(): void
    {
        foreach (['books', 'authors', 'users', 'categories', 'reviews', 'roles'] as $resource) {
            $this->actingAs($this->reader)->get("/admin/{$resource}")->assertForbidden();
        }
    }
}
