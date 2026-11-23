<?php

namespace Tests\Feature\Author;

use App\Models\Author;
use App\Models\AuthorApplication;
use App\Models\User;
use App\Services\AdminService;
use App\Services\AuthorService;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

class AuthorApplicationTest extends AuthorTestCase
{
    public function test_apply_page_renders_for_reader(): void
    {
        $this->actingReader()
            ->get('/bn/author/apply')
            ->assertOk()
            ->assertSee(__('author.apply_heading'));
    }

    public function test_apply_requires_valid_bkash(): void
    {
        $this->actingReader();

        // A bad bKash number passes Livewire's string rule, then the
        // service rejects it — surfaced as the component error.
        Livewire::test('author-application-form')
            ->set('bkashNumber', 'not-a-number')
            ->set('document', UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'))
            ->call('submit')
            ->assertSet('error', 'Invalid bKash number.');

        $this->assertSame(0, AuthorApplication::count());
    }

    public function test_apply_without_document_is_rejected(): void
    {
        $this->actingReader();

        Livewire::test('author-application-form')
            ->set('bkashNumber', '01712345678')
            ->call('submit')
            ->assertHasErrors(['document']);
        $this->assertSame(0, AuthorApplication::count());
    }

    public function test_apply_creates_pending_author_and_submitted_application(): void
    {
        $this->actingReader();

        Livewire::test('author-application-form')
            ->set('penName', 'Test Pen')
            ->set('bio', 'A test bio.')
            ->set('bkashNumber', '01712345678')
            ->set('document', UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'))
            ->call('submit')
            ->assertHasNoErrors();

        $application = AuthorApplication::firstOrFail();
        $this->assertSame('submitted', $application->status);
        $this->assertSame('Test Pen', $application->pen_name);
        $this->assertSame('01712345678', $application->bkash_number); // encrypted cast decrypts
        $this->assertNotNull($application->id_document_path);

        $author = Author::where('user_id', $this->reader->id)->firstOrFail();
        $this->assertSame('pending', $author->status);
        $this->assertSame($author->id, $application->author_id);
    }

    public function test_pending_application_blocks_reapply_and_shows_status(): void
    {
        $service = app(AuthorService::class);
        $service->submitApplication($this->reader, [
            'bkash_number' => '01712345678',
        ], UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'));

        $this->assertTrue($service->activeApplicationFor($this->reader) !== null);

        // Second submit throws (one active application per account).
        $this->expectException(\InvalidArgumentException::class);
        $service->submitApplication($this->reader, [
            'bkash_number' => '01712345678',
        ], UploadedFile::fake()->create('id2.pdf', 100, 'application/pdf'));
    }

    public function test_pending_application_page_shows_status_not_form(): void
    {
        app(AuthorService::class)->submitApplication($this->reader, [
            'bkash_number' => '01712345678',
        ], UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'));

        $this->actingReader()
            ->get('/bn/author/apply')
            ->assertOk()
            ->assertSee(__('author.apply_pending'));
    }

    public function test_admin_approval_grants_author_role_and_opens_studio(): void
    {
        $service = app(AuthorService::class);
        $application = $service->submitApplication($this->reader, [
            'bkash_number' => '01712345678',
        ], UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'));

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $service->reviewApplication($application, $admin, true, 'Looks good.');

        $this->assertSame('approved', $application->fresh()->status);
        $this->assertTrue($this->reader->fresh()->hasRole('author'));
        $this->assertSame('approved', Author::where('user_id', $this->reader->id)->first()->status);

        // Approved author goes straight to the Studio, not the form.
        $this->actingReader()
            ->get('/bn/author/apply')
            ->assertRedirect('/bn/author');
    }

    public function test_admin_rejection_records_reason(): void
    {
        $service = app(AuthorService::class);
        $application = $service->submitApplication($this->reader, [
            'bkash_number' => '01712345678',
        ], UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'));

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $service->reviewApplication($application, $admin, false, 'ID document unclear.');

        $this->assertSame('rejected', $application->fresh()->status);
        $this->assertSame('ID document unclear.', $application->fresh()->review_note);
        $this->assertFalse($this->reader->fresh()->hasRole('author'));
    }

    public function test_approved_author_dashboard_renders(): void
    {
        $this->actingAuthor()
            ->get('/bn/author')
            ->assertOk()
            ->assertSee(__('author.dashboard_title'))
            ->assertSee('৳0'); // no earnings table yet — zeroes, never hardcoded
    }

    public function test_reader_cannot_open_dashboard(): void
    {
        $this->actingReader()->get('/bn/author')->assertForbidden();
    }
}
