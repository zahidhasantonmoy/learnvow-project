<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\Highlight;
use Livewire\Component;

/**
 * Quote card (§4.6): pick a theme (3), share via Web Share API, copy
 * text, or export PNG client-side via canvas. Nothing is stored (A14).
 */
class QuoteCard extends Component
{
    public Book $book;

    public ?Highlight $highlight = null;

    public string $theme = 'paper';

    public const THEMES = ['paper', 'night', 'sepia'];

    public function mount(Book $book, ?Highlight $highlight = null): void
    {
        $this->book = $book;
        $this->highlight = $highlight ?? Highlight::where('user_id', auth()->id())
            ->where('book_id', $book->id)->latest()->first();
    }

    public function selectTheme(string $theme): void
    {
        if (in_array($theme, self::THEMES, true)) {
            $this->theme = $theme;
        }
    }

    public function quote(): string
    {
        $text = $this->highlight?->note ?? '';
        if (mb_strlen($text) > 280) {
            $text = mb_substr($text, 0, 279).'…';
        }

        return $text;
    }

    public function render()
    {
        return view('livewire.quote-card', [
            'quote' => $this->quote(),
            'themes' => self::THEMES,
        ]);
    }
}
