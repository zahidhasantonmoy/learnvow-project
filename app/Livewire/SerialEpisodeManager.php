<?php

namespace App\Livewire;

use App\Http\Controllers\SerialController;
use App\Models\Author;
use App\Models\Serial;
use Livewire\Component;

/**
 * Author adds episodes to a serial (§4.7, Author Studio side).
 * Publishing notifies the serial author's followers (§4.7).
 */
class SerialEpisodeManager extends Component
{
    public Serial $serial;

    public string $title = '';

    public string $content = '';

    public bool $publishNow = true;

    public bool $isFree = true;

    public function mount(Serial $serial): void
    {
        $this->authorizeSerial($serial);
        $this->serial = $serial;
    }

    public function addEpisode(): void
    {
        $this->authorizeSerial($this->serial);
        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        if ($this->serial->episodes()->count() >= Serial::MAX_EPISODES) {
            $this->addError('title', __('books.serial_episode_limit', ['max' => Serial::MAX_EPISODES]));
            return;
        }

        $episodeNo = (int) ($this->serial->episodes()->max('episode_no') ?? 0) + 1;

        $episode = $this->serial->episodes()->create([
            'episode_no' => $episodeNo,
            'title' => $this->title,
            'content' => $this->content,
            'published_at' => $this->publishNow ? now() : null,
            'is_free' => $this->isFree,
        ]);

        $this->reset(['title', 'content']);

        if ($episode->published_at !== null) {
            SerialController::notifyFollowers($this->serial, $episode);
        }

        session()->flash('status', __('books.serial_episode_added'));
    }

    public function publishEpisode(int $episodeId): void
    {
        $this->authorizeSerial($this->serial);
        $episode = $this->serial->episodes()->findOrFail($episodeId);
        $episode->published_at = now();
        $episode->save();
        SerialController::notifyFollowers($this->serial, $episode);
        session()->flash('status', __('books.serial_episode_published'));
    }

    protected function authorizeSerial(Serial $serial): void
    {
        $user = auth()->user();
        $author = Author::where('user_id', $user->id)->first();
        abort_unless(
            ($author !== null && $serial->author_id === $author->id)
                || \App\Services\AdminService::isAdmin($user),
            403
        );
    }

    public function render()
    {
        return view('livewire.serial-episode-manager', [
            'episodes' => $this->serial->episodes()->orderBy('episode_no')->get(),
        ]);
    }
}
