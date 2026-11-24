<?php

namespace Tests\Feature\International;

use App\Models\Author;
use App\Models\Book;
use App\Models\TaxKycProfile;
use App\Models\User;
use App\Policies\TaxKycPolicy;
use App\Services\AuthorService;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 spec §3.3 — Tax & KYC submit + Super Admin verify/reject.
 * International checkout is Builder B's — not tested here.
 */
class TaxKycTest extends TestCase
{
    use RefreshDatabase;

    protected User $authorUser;

    protected Author $author;

    protected User $superAdmin;

    protected User $admin;

    protected User $otherReader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        // TaxKycPolicy is named per the task (not the auto-discovered
        // TaxKycProfilePolicy), so register it explicitly here. The
        // coordinator must add the same Gate::policy() line in
        // AppServiceProvider for production use.
        \Illuminate\Support\Facades\Gate::policy(TaxKycProfile::class, TaxKycPolicy::class);

        $this->seed([
            RolePermissionSeeder::class, CategorySeeder::class,
            AuthorSeeder::class, BookSeeder::class,
        ]);

        $this->authorUser = User::factory()->create(['phone' => '01700000003', 'name' => 'Demo Author']);
        $this->authorUser->assignRole('reader');
        $this->authorUser->assignRole('author');

        $this->author = Author::where('slug', 'learnvow-commissioned-series')->firstOrFail();
        $this->author->update(['user_id' => $this->authorUser->id, 'status' => 'approved']);

        $this->superAdmin = User::factory()->create(['phone' => '01700000012']);
        $this->superAdmin->assignRole('super-admin');

        $this->admin = User::factory()->create(['phone' => '01700000011']);
        $this->admin->assignRole('admin');

        $this->otherReader = User::factory()->create(['phone' => '01700000002']);
        $this->otherReader->assignRole('reader');
    }

    public function test_author_submits_kyc_and_it_is_pending_and_encrypted(): void
    {
        $this->actingAs($this->authorUser);

        $this->get('/bn/author/tax-kyc')->assertOk();

        $this->put('/bn/author/tax-kyc', [
            'legal_name' => 'Demo Author Legal',
            'nid_no' => '1234567890',
            'tin_no' => '987654321',
            'address' => 'Dhaka',
            'bank_account_name' => 'Demo Author',
            'bank_account_number' => '0011223344',
        ])->assertRedirect();

        $profile = TaxKycProfile::where('author_id', $this->author->id)->firstOrFail();
        $this->assertSame('pending', $profile->status);
        $this->assertSame('Demo Author Legal', $profile->legal_name);

        // Encrypted at rest: raw DB value is not the plain number.
        $raw = \Illuminate\Support\Facades\DB::table('tax_kyc_profiles')
            ->where('id', $profile->id)->value('nid_no');
        $this->assertNotSame('1234567890', $raw);
        $this->assertSame('1234567890', $profile->fresh()->nid_no);

        // Page shows only the last 3 digits, never the full number.
        $this->get('/bn/author/tax-kyc')->assertOk()
            ->assertDontSee('1234567890')
            ->assertSee('•••890')
            ->assertDontSee('0011223344')
            ->assertSee('•••344');
    }

    public function test_only_super_admin_can_review_kyc(): void
    {
        $profile = TaxKycProfile::create([
            'author_id' => $this->author->id,
            'legal_name' => 'Demo Author Legal',
            'nid_no' => '1234567890',
            'status' => 'pending',
        ]);

        // Admin (not Super Admin) may not review.
        $this->assertFalse($this->admin->can('review', $profile));
        // The author themself may not review.
        $this->assertFalse($this->authorUser->can('review', $profile));
        // Super Admin may.
        $this->assertTrue($this->superAdmin->can('review', $profile));

        // Other readers cannot even view it.
        $this->assertFalse($this->otherReader->can('view', $profile));
        $this->assertTrue($this->authorUser->can('view', $profile));
    }

    public function test_super_admin_verify_and_reject_flow(): void
    {
        $profile = TaxKycProfile::create([
            'author_id' => $this->author->id,
            'legal_name' => 'Demo Author Legal',
            'nid_no' => '1234567890',
            'status' => 'pending',
        ]);

        // Verify (mirrors the Filament ViewTaxKycProfile action).
        $profile->update([
            'status' => TaxKycProfile::STATUS_VERIFIED,
            'reviewed_by' => $this->superAdmin->id,
        ]);
        $this->assertSame('verified', $profile->fresh()->status);
        $this->assertSame($this->superAdmin->id, $profile->fresh()->reviewed_by);

        // Reject with a reason (mirrors the Filament reject action).
        $profile->update([
            'status' => TaxKycProfile::STATUS_REJECTED,
            'reviewed_by' => $this->superAdmin->id,
            'review_note' => 'NID is not readable.',
        ]);
        $this->assertSame('rejected', $profile->fresh()->status);
        $this->assertSame('NID is not readable.', $profile->fresh()->review_note);

        // Resubmission goes back to pending.
        $this->actingAs($this->authorUser);
        $this->put('/bn/author/tax-kyc', [
            'legal_name' => 'Demo Author Legal',
            'nid_no' => '1234567890',
        ])->assertRedirect();
        $this->assertSame('pending', $profile->fresh()->status);
    }

    public function test_non_author_cannot_open_tax_kyc(): void
    {
        $this->actingAs($this->otherReader);
        $this->get('/bn/author/tax-kyc')->assertForbidden();
    }
}
