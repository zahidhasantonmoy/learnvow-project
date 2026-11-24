<?php

namespace Tests\Feature\Reader;

use App\Models\VocabularyWord;

class VocabularyTest extends ReaderTestCase
{
    public function test_vocabulary_add_delete_and_unique(): void
    {
        $this->actingAs($this->reader);

        $this->post('/bn/vocabulary', [
            'word' => 'Serendipity', 'meaning_en' => 'pleasant surprise', 'book_id' => $this->gitanjali->id,
        ])->assertRedirect();

        // Stored lowercase, once only.
        $this->post('/bn/vocabulary', ['word' => 'serendipity', 'meaning_en' => 'again'])->assertRedirect();
        $this->assertEquals(1, VocabularyWord::where('user_id', $this->reader->id)->count());
        $word = VocabularyWord::where('user_id', $this->reader->id)->firstOrFail();
        $this->assertEquals('serendipity', $word->word);
        $this->assertEquals('pleasant surprise', $word->meaning_en);

        $this->get('/bn/vocabulary')->assertOk()->assertSee('serendipity');
        $this->get('/bn/vocabulary?q=seren')->assertOk()->assertSee('serendipity');
        $this->get('/bn/vocabulary?q=zzz')->assertOk()->assertDontSee('serendipity');

        $this->delete("/bn/vocabulary/{$word->id}")->assertRedirect();
        $this->assertDatabaseMissing('vocabulary_words', ['id' => $word->id]);
    }

    public function test_vocabulary_livewire_manager(): void
    {
        $this->actingAs($this->reader);

        \Livewire\Livewire::test(\App\Livewire\VocabularyManager::class)
            ->set('word', 'Ephemeral')
            ->set('meaningEn', 'short-lived')
            ->call('addWord')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vocabulary_words', ['user_id' => $this->reader->id, 'word' => 'ephemeral']);
    }

    public function test_reader_view_add_to_vocabulary(): void
    {
        $this->actingAs($this->reader);

        \Livewire\Livewire::test(\App\Livewire\ReaderView::class, ['book' => $this->gitanjali])
            ->call('addToVocabulary', 'Luminous', 'full of light')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('vocabulary_words', [
            'user_id' => $this->reader->id,
            'word' => 'luminous',
            'meaning_en' => 'full of light',
            'book_id' => $this->gitanjali->id,
        ]);
    }

    public function test_other_users_word_cannot_be_deleted(): void
    {
        $word = VocabularyWord::create(['user_id' => $this->reader->id, 'word' => 'zephyr']);
        $this->actingAs($this->otherReader());
        $this->delete("/bn/vocabulary/{$word->id}")->assertForbidden();
    }
}
