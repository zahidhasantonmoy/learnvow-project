<?php

namespace Tests\Feature\Reader;

use App\Models\Author;
use App\Models\Category;
use App\Models\TasteProfile;

class TasteProfileTest extends ReaderTestCase
{
    public function test_taste_profile_save_and_update(): void
    {
        $this->actingAs($this->reader);
        $poetry = Category::where('slug', 'poetry')->firstOrFail();
        $tagore = Author::where('slug', 'rabindranath-tagore')->firstOrFail();

        $this->put('/bn/profile/taste', [
            'genres' => [$poetry->id],
            'authors' => [$tagore->id],
            'languages' => ['bn', 'en'],
            'reading_time' => 'night',
        ])->assertRedirect();

        $profile = TasteProfile::where('user_id', $this->reader->id)->firstOrFail();
        $this->assertEquals([$poetry->id], $profile->genreIds());
        $this->assertEquals([$tagore->id], $profile->authorIds());
        $this->assertEquals(['bn', 'en'], $profile->languages);
        $this->assertEquals('night', $profile->reading_time);

        // One row per user — saving again updates, never duplicates.
        $this->put('/bn/profile/taste', ['genres' => [], 'authors' => [], 'languages' => ['bn']])
            ->assertRedirect();
        $this->assertEquals(1, TasteProfile::where('user_id', $this->reader->id)->count());
        $this->assertNull($profile->fresh()->reading_time);

        $this->get('/bn/profile/taste')->assertOk();
    }

    public function test_taste_profile_form_livewire(): void
    {
        $this->actingAs($this->reader);
        $novel = Category::where('slug', 'novel')->firstOrFail();

        \Livewire\Livewire::test(\App\Livewire\TasteProfileForm::class)
            ->set('genres', [$novel->id])
            ->set('languages', ['en'])
            ->set('readingTime', 'morning')
            ->call('save')
            ->assertHasNoErrors();

        $profile = TasteProfile::where('user_id', $this->reader->id)->firstOrFail();
        $this->assertEquals([$novel->id], $profile->genreIds());
        $this->assertEquals('morning', $profile->reading_time);
    }
}
