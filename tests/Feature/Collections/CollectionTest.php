<?php

namespace Tests\Feature\Collections;

use App\Models\Book;
use App\Models\Collection;
use Tests\Feature\Reader\ReaderTestCase;

class CollectionTest extends ReaderTestCase
{
    public function test_collections_crud_and_limits(): void
    {
        $this->actingAs($this->reader);

        // Create.
        $this->post('/bn/collections', ['name' => 'প্রিয় কবিতা'])->assertRedirect();
        $this->assertDatabaseHas('collections', ['user_id' => $this->reader->id, 'name' => 'প্রিয় কবিতা']);
        $collection = Collection::where('user_id', $this->reader->id)->firstOrFail();

        // Duplicate name for the same user is rejected.
        $this->post('/bn/collections', ['name' => 'প্রিয় কবিতা'])->assertSessionHasErrors('name');

        // Index renders.
        $this->get('/bn/collections')->assertOk()->assertSee('প্রিয় কবিতা');

        // Add a book, then add the same book again (no duplicate row).
        $this->post("/bn/collections/{$collection->id}/books/{$this->prophet->slug}")->assertRedirect();
        $this->post("/bn/collections/{$collection->id}/books/{$this->prophet->slug}")->assertRedirect();
        $this->assertEquals(1, $collection->fresh()->items()->count());

        // Show renders the book.
        $this->get("/bn/collections/{$collection->id}")->assertOk()->assertSee('The Prophet');

        // Remove the book.
        $this->delete("/bn/collections/{$collection->id}/books/{$this->prophet->slug}")->assertRedirect();
        $this->assertEquals(0, $collection->fresh()->items()->count());

        // 20-shelf limit: fill up to 20, the 21st is rejected.
        for ($i = 2; $i <= 20; $i++) {
            Collection::create(['user_id' => $this->reader->id, 'name' => 'Shelf '.$i]);
        }
        $this->post('/bn/collections', ['name' => 'Shelf 21'])->assertSessionHasErrors('name');
        $this->assertEquals(20, Collection::where('user_id', $this->reader->id)->count());

        // Delete the shelf — the book itself stays.
        $this->delete("/bn/collections/{$collection->id}")->assertRedirect();
        $this->assertDatabaseMissing('collections', ['id' => $collection->id]);
        $this->assertDatabaseHas('books', ['id' => $this->prophet->id]);
    }

    public function test_other_users_collection_is_forbidden(): void
    {
        $collection = Collection::create(['user_id' => $this->reader->id, 'name' => 'Mine']);
        $other = $this->otherReader();
        $this->actingAs($other);

        $this->get("/bn/collections/{$collection->id}")->assertForbidden();
        $this->delete("/bn/collections/{$collection->id}")->assertForbidden();
    }

    public function test_livewire_collection_manager_creates_and_adds(): void
    {
        $this->actingAs($this->reader);

        \Livewire\Livewire::test(\App\Livewire\CollectionManager::class)
            ->set('name', 'পরে পড়বো')
            ->call('createCollection')
            ->assertHasNoErrors();

        $collection = Collection::where('user_id', $this->reader->id)->firstOrFail();
        $this->assertEquals('পরে পড়বো', $collection->name);

        \Livewire\Livewire::test(\App\Livewire\CollectionManager::class, ['collectionId' => $collection->id])
            ->call('addBook', $this->gitanjali->id)
            ->assertHasNoErrors();

        $this->assertEquals(1, $collection->fresh()->items()->count());
    }
}
