<?php

namespace App\Livewire;

use App\Models\Book;
use App\Services\BookService;
use App\Services\ReaderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * ReadingSettings — per-book font / size / theme / spacing (§1.2).
 * Every change saves immediately (the Blade input uses
 * wire:model.live.debounce.500ms) — there is no Save button. A failed
 * save dispatches `settings-save-failed` so the page can toast.
 */
class ReadingSettings extends Component
{
    public Book $book;

    public string $fontFamily = 'serif';

    public int $fontSize = 100;

    public float $lineHeight = 1.60;

    public string $theme = 'paper';

    public int $brightness = 100;

    public string $readingMode = 'paged';

    public function mount(Book $book): void
    {
        $this->book = $book;

        $settings = app(ReaderService::class)->settingsFor(Auth::user(), $book);

        $this->fontFamily = $settings->font_family;
        $this->fontSize = $settings->font_size;
        $this->lineHeight = (float) $settings->line_height;
        $this->theme = $settings->theme;
        $this->brightness = $settings->brightness;
        $this->readingMode = $settings->reading_mode;
    }

    public function updated(): void
    {
        $this->save();
    }

    public function save(): void
    {
        abort_unless(app(BookService::class)->canRead(Auth::user(), $this->book), 403);

        try {
            $settings = app(ReaderService::class)->saveSettings(Auth::user(), $this->book, [
                'font_family' => $this->fontFamily,
                'font_size' => $this->fontSize,
                'line_height' => $this->lineHeight,
                'theme' => $this->theme,
                'brightness' => $this->brightness,
                'reading_mode' => $this->readingMode,
            ]);

            $this->fontFamily = $settings->font_family;
            $this->fontSize = $settings->font_size;
            $this->lineHeight = (float) $settings->line_height;
            $this->theme = $settings->theme;
            $this->brightness = $settings->brightness;
            $this->readingMode = $settings->reading_mode;

            $this->dispatch('settings-saved', settings: $settings->only([
                'font_family', 'font_size', 'line_height', 'theme', 'brightness', 'reading_mode',
            ]));
        } catch (\Throwable) {
            $this->dispatch('settings-save-failed');
        }
    }

    public function render()
    {
        return view('livewire.reading-settings');
    }
}
