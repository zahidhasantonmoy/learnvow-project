<?php

namespace Tests\Feature\Reader;

use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use Livewire\Livewire;

class BookmarkHighlightNoteTest extends ReaderTestCase
{
    public function test_bookmark_crud_and_ownership(): void
    {
        // Store via JSON.
        $this->actingAs($this->reader)->postJson('/bn/reader/the-prophet/bookmarks', [
            'cfi' => 'epubcfi(/6/2[chapter-1]!/4/2)',
            'label' => 'Favourite passage',
        ])->assertCreated();

        $bookmark = Bookmark::firstOrFail();
        $this->assertSame('Favourite passage', $bookmark->label);

        // Another reader cannot delete it (own rows only).
        $this->actingAs($this->otherReader())
            ->deleteJson("/bn/reader/the-prophet/bookmarks/{$bookmark->id}")
            ->assertForbidden();
        $this->assertSame(1, Bookmark::count());

        // Owner deletes — hard delete.
        $this->actingAs($this->reader)
            ->deleteJson("/bn/reader/the-prophet/bookmarks/{$bookmark->id}")
            ->assertOk();
        $this->assertSame(0, Bookmark::count());

        // Cannot bookmark a book the user may not read.
        $this->actingAs($this->otherReader())->postJson('/bn/reader/the-prophet/bookmarks', [
            'cfi' => 'epubcfi(/6/2)',
        ])->assertForbidden();
    }

    public function test_highlight_and_note_crud_and_ownership(): void
    {
        $this->actingAs($this->reader)->postJson('/bn/reader/the-prophet/highlights', [
            'cfi_start' => 'epubcfi(/6/2!/4/2)',
            'cfi_end' => 'epubcfi(/6/2!/4/4)',
            'color' => 'green',
            'note' => 'Remember this',
        ])->assertCreated();
        $this->assertSame('green', Highlight::firstOrFail()->color);

        $this->actingAs($this->reader)->postJson('/bn/reader/the-prophet/notes', [
            'cfi' => 'epubcfi(/6/2!/4/2)',
            'body' => 'My note on this passage.',
        ])->assertCreated();
        $this->assertSame('My note on this passage.', Note::firstOrFail()->body);

        // Invalid colour is rejected.
        $this->actingAs($this->reader)->postJson('/bn/reader/the-prophet/highlights', [
            'cfi_start' => 'epubcfi(/6/2!/4/2)',
            'cfi_end' => 'epubcfi(/6/2!/4/4)',
            'color' => 'red',
        ])->assertUnprocessable();

        // Bookmarks page lists all three kinds for the owner.
        $response = $this->actingAs($this->reader)->get('/bn/bookmarks');
        $response->assertOk();
        $response->assertSee('Remember this');
        $response->assertSee('My note on this passage.');

        // Another reader sees none of them.
        $other = $this->actingAs($this->otherReader())->get('/bn/bookmarks');
        $other->assertOk();
        $other->assertDontSee('Remember this');
        $other->assertDontSee('My note on this passage.');
    }

    public function test_reader_view_refreshes_bookmark_list_after_add(): void
    {
        $component = Livewire::actingAs($this->reader)
            ->test('reader-view', ['book' => $this->prophet])
            ->assertSee(__('reader.no_bookmarks'));

        // Bookmark added outside Livewire (the REST endpoint, as reader.js does).
        $this->actingAs($this->reader)->postJson('/bn/reader/the-prophet/bookmarks', [
            'cfi' => 'epubcfi(/6/2[chapter-1]!/4/2)',
            'label' => 'Favourite passage',
        ])->assertCreated();

        // The live component only re-renders when `bookmark-added` arrives.
        $component->dispatch('bookmark-added')
            ->assertSee('Favourite passage')
            ->assertDontSee(__('reader.no_bookmarks'));
    }

    public function test_bookmark_manager_livewire_deletes_only_own_rows(): void
    {
        $mine = Bookmark::create([
            'user_id' => $this->reader->id, 'book_id' => $this->prophet->id,
            'cfi' => 'epubcfi(/6/2)', 'label' => 'Mine',
        ]);
        $theirs = Bookmark::create([
            'user_id' => $this->otherReader()->id, 'book_id' => $this->prophet->id,
            'cfi' => 'epubcfi(/6/4)',
        ]);

        Livewire::actingAs($this->reader)
            ->test('bookmark-manager')
            ->call('deleteBookmark', $theirs->id)
            ->assertForbidden();
        $this->assertSame(2, Bookmark::count());

        Livewire::actingAs($this->reader)
            ->test('bookmark-manager')
            ->call('deleteBookmark', $mine->id);
        $this->assertSame(1, Bookmark::count());
    }
}
