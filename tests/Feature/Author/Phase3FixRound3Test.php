<?php

namespace Tests\Feature\Author;

use App\Livewire\AuthorApplicationForm;
use App\Livewire\AuthorAudioUpload;
use App\Livewire\AuthorBookEditor;
use App\Livewire\AuthorUpload;
use Livewire\Livewire;

/**
 * Fix round 3 (final verification 4/5 PASS, 1 FAIL — 5a): the author
 * apply form's HTML `required` + no `novalidate` let the browser's native
 * constraint validation block the submit before Livewire ever ran, so the
 * Bengali server-side error never rendered (the browser showed its own
 * English "Please select a file."). Same trap class in the publish,
 * audio-upload and book-editor forms; those also fell back to Laravel's
 * English defaults for required fields and two of them had no @error
 * directives at all. Fix: novalidate on all four Livewire forms,
 * localized *.required messages, and the missing @error directives.
 * These tests assert the rendered HTML (novalidate) as well as the
 * localized messages — the Livewire-only round-2 test passed while the
 * browser behaviour was still broken.
 */
class Phase3FixRound3Test extends AuthorTestCase
{
    public function test_apply_form_renders_novalidate(): void
    {
        $this->actingReader()
            ->get('/bn/author/apply')
            ->assertOk()
            ->assertSee('novalidate', false);
    }

    public function test_publish_form_renders_novalidate(): void
    {
        $this->actingAuthor()
            ->get('/bn/author/publish')
            ->assertOk()
            ->assertSee('novalidate', false);
    }

    public function test_audio_and_editor_forms_render_novalidate(): void
    {
        // The seeded Prophet belongs to another author, so the edit/audio
        // pages (Gate: manageBook) need a book owned by the test author.
        $book = $this->prophet->replicate();
        $book->slug = 'round3-test-book';
        $book->author_id = $this->author->id;
        $book->save();

        $this->actingAuthor()
            ->get('/bn/author/books/'.$book->slug.'/audio')
            ->assertOk()
            ->assertSee('novalidate', false);

        $this->actingAuthor()
            ->get('/bn/author/books/'.$book->slug.'/edit')
            ->assertOk()
            ->assertSee('novalidate', false);
    }

    public function test_apply_bkash_error_is_bengali_on_bn_page(): void
    {
        app()->setLocale('bn');
        $this->actingAs($this->reader);

        Livewire::test(AuthorApplicationForm::class)
            ->set('bkashNumber', '')
            ->call('submit')
            ->assertHasErrors(['bkashNumber'])
            ->assertSee(__('author.apply_bkash_required'));
    }

    public function test_apply_document_error_is_bengali_on_bn_page(): void
    {
        app()->setLocale('bn');
        $this->actingAs($this->reader);

        Livewire::test(AuthorApplicationForm::class)
            ->set('bkashNumber', '01700000002')
            ->call('submit')
            ->assertHasErrors(['document'])
            ->assertSee(__('author.apply_document_required'));
    }

    public function test_apply_document_error_is_english_on_en_page(): void
    {
        app()->setLocale('en');
        $this->actingAs($this->reader);

        Livewire::test(AuthorApplicationForm::class)
            ->set('bkashNumber', '01700000002')
            ->call('submit')
            ->assertHasErrors(['document'])
            ->assertSee(__('author.apply_document_required'));
    }

    public function test_publish_required_errors_are_bengali_on_bn_page(): void
    {
        app()->setLocale('bn');
        $this->actingAs($this->authorUser);

        Livewire::test(AuthorUpload::class)
            ->call('submit')
            ->assertHasErrors(['title', 'description', 'categoryId', 'bookFile'])
            ->assertSee(__('author.publish_title_required'))
            ->assertSee(__('author.publish_description_required'))
            ->assertSee(__('author.publish_category_required'))
            ->assertSee(__('author.publish_file_required'));
    }

    public function test_audio_required_error_is_bengali_on_bn_page(): void
    {
        app()->setLocale('bn');
        $this->actingAs($this->authorUser);

        Livewire::test(AuthorAudioUpload::class, ['book' => $this->prophet])
            ->call('submit')
            ->assertHasErrors(['audio'])
            ->assertSee(__('author.audio_file_required'));
    }

    public function test_editor_description_error_is_bengali_on_bn_page(): void
    {
        app()->setLocale('bn');
        $this->actingAs($this->authorUser);

        Livewire::test(AuthorBookEditor::class, ['book' => $this->prophet])
            ->set('description', '')
            ->call('save')
            ->assertHasErrors(['description'])
            ->assertSee(__('author.edit_description_required'));
    }
}
