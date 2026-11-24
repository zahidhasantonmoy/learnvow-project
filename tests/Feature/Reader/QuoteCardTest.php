<?php

namespace Tests\Feature\Reader;

use App\Models\Highlight;

class QuoteCardTest extends ReaderTestCase
{
    public function test_quote_card_renders_from_own_highlight(): void
    {
        $highlight = Highlight::create([
            'user_id' => $this->reader->id,
            'book_id' => $this->gitanjali->id,
            'cfi_start' => 'epubcfi(/6/2)',
            'cfi_end' => 'epubcfi(/6/4)',
            'note' => 'Where the mind is without fear.',
        ]);

        $this->actingAs($this->reader);
        $this->get("/bn/reader/{$this->gitanjali->slug}/quote-card?highlight={$highlight->id}")
            ->assertOk()
            ->assertSee('Where the mind is without fear.')
            ->assertSee('Gitanjali')
            ->assertSee('LearnVow');
    }

    public function test_quote_card_truncates_over_280_chars(): void
    {
        $long = str_repeat('a', 300);
        $highlight = Highlight::create([
            'user_id' => $this->reader->id,
            'book_id' => $this->gitanjali->id,
            'cfi_start' => 'epubcfi(/6/2)',
            'cfi_end' => 'epubcfi(/6/4)',
            'note' => $long,
        ]);

        $this->actingAs($this->reader);
        $response = $this->get("/bn/reader/{$this->gitanjali->slug}/quote-card?highlight={$highlight->id}");
        $response->assertOk();
        $this->assertStringContainsString('…', $response->getContent());
        $this->assertStringNotContainsString($long, $response->getContent());
    }

    public function test_quote_card_without_highlight_shows_empty_state(): void
    {
        // Regression (QA M2): no highlight used to be a bare 404.
        $this->actingAs($this->reader);
        $this->get("/bn/reader/{$this->gitanjali->slug}/quote-card")
            ->assertOk()
            ->assertSee('এখনো কোনো লাইন হাইলাইট করেননি');

        $this->get("/en/reader/{$this->gitanjali->slug}/quote-card")
            ->assertOk()
            ->assertSee('You have not highlighted a line yet');
    }

    public function test_quote_card_requires_own_highlight(): void
    {
        $other = $this->otherReader();
        $highlight = Highlight::create([
            'user_id' => $other->id,
            'book_id' => $this->gitanjali->id,
            'cfi_start' => 'epubcfi(/6/2)',
            'cfi_end' => 'epubcfi(/6/4)',
            'note' => 'Not mine.',
        ]);

        $this->actingAs($this->reader);
        $this->get("/bn/reader/{$this->gitanjali->slug}/quote-card?highlight={$highlight->id}")
            ->assertNotFound();
    }

    public function test_quote_card_livewire_theme_switch(): void
    {
        Highlight::create([
            'user_id' => $this->reader->id,
            'book_id' => $this->gitanjali->id,
            'cfi_start' => 'epubcfi(/6/2)',
            'cfi_end' => 'epubcfi(/6/4)',
            'note' => 'A short line.',
        ]);

        $this->actingAs($this->reader);
        \Livewire\Livewire::test(\App\Livewire\QuoteCard::class, ['book' => $this->gitanjali])
            ->call('selectTheme', 'night')
            ->assertSet('theme', 'night')
            ->assertSee('A short line.');
    }
}
