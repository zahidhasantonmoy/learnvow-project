<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AdminService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Coordinator fix (re-QA Minor): Filament's default UiAvatarsProvider
 * calls ui-avatars.com, which fails offline and logs console errors on
 * every admin page. The panel uses a self-hosted SVG data-URI avatar
 * (LocalAvatarProvider) instead — no external request.
 */
class AdminAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_pages_use_no_external_avatar_service(): void
    {
        Role::create(['name' => 'admin', 'guard_name' => 'web']);

        $admin = User::factory()->create();
        AdminService::assignRole($admin, 'admin');

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('ui-avatars.com')
            ->assertSee('data:image/svg+xml', false);
    }
}
