<?php

namespace App\Livewire;

use App\Models\Book;
use App\Services\BookService;
use App\Services\ReaderService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * AudioPlayer — recorded-audiobook controls (§1.6): chapter, speed
 * 0.75×–2.0×, ±10s / ±30s skip, sleep timer, listening progress
 * (listening counts as reading for the pool, §1.3). The <audio>
 * element itself lives in audio/player; this component owns state.
 */
class AudioPlayer extends Component
{
    public Book $book;

    public float $speed = 1.0;

    public int $chapterIndex = 0;

    public int $listenedSeconds = 0;

    public ?int $sleepMinutes = null;

    public bool $playing = false;

    public function mount(Book $book): void
    {
        $this->book = $book;

        $progress = app(ReaderService::class)->progressFor(Auth::user(), $book);

        if ($progress !== null) {
            $this->chapterIndex = $progress->chapter_index;
        }
    }

    /** Cycle 1.0× → 1.25× → 1.5× → 2.0× → 0.75× (prototype order). */
    public function cycleSpeed(): void
    {
        $this->speed = match ($this->speed) {
            1.0 => 1.25,
            1.25 => 1.5,
            1.5 => 2.0,
            2.0 => 0.75,
            default => 1.0,
        };
    }

    public function setSleepTimer(?int $minutes): void
    {
        $this->sleepMinutes = $minutes !== null && $minutes > 0 ? $minutes : null;
    }

    /** Persist listening time as reading progress (minutes, cumulative). */
    public function saveListeningProgress(int $seconds): void
    {
        abort_unless(app(BookService::class)->canRead(Auth::user(), $this->book), 403);

        $this->listenedSeconds = max(0, $seconds);

        $existing = app(ReaderService::class)->progressFor(Auth::user(), $this->book);
        $minutes = (int) floor($seconds / 60);

        app(ReaderService::class)->saveProgress(Auth::user(), $this->book, [
            'chapter_index' => $this->chapterIndex,
            'total_minutes' => max($existing->total_minutes ?? 0, $minutes),
            'last_read_at' => now()->toISOString(),
        ]);
    }

    public function render()
    {
        return view('livewire.audio-player');
    }
}
