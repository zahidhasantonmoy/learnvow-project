<?php

namespace App\Livewire;

use App\Models\Author;
use App\Models\Follow;
use Livewire\Component;

class FollowButton extends Component
{
    public Author $author;
    public bool $isFollowing = false;
    public int $followersCount = 0;

    public function mount(Author $author, bool $isFollowing = false, int $followersCount = 0): void
    {
        $this->author = $author;
        $this->isFollowing = $isFollowing;
        $this->followersCount = $followersCount;
    }

    public function toggle(): void
    {
        $user = auth()->user();
        if (! $user) {
            $this->redirectRoute('login', ['locale' => app()->getLocale()]);
            return;
        }

        $existing = Follow::where('user_id', $user->id)->where('author_id', $this->author->id)->first();

        if ($existing) {
            $existing->delete();
            $this->isFollowing = false;
            $this->followersCount = max(0, $this->followersCount - 1);
        } else {
            Follow::create(['user_id' => $user->id, 'author_id' => $this->author->id]);
            $this->isFollowing = true;
            $this->followersCount++;
        }
    }

    public function render()
    {
        return view('livewire.follow-button');
    }
}
