<?php

namespace App\Livewire;

use App\Models\Club;
use App\Models\CommunityPost;
use App\Services\CommunityService;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Club discussion (Phase 4 spec §6.2, §16). Members post; own posts
 * and moderator/staff deletions go through CommunityService.
 */
class ClubDiscussion extends Component
{
    public Club $club;

    public string $body = '';

    public string $type = CommunityPost::TYPE_DISCUSSION;

    public ?int $book_id = null;

    public ?string $error = null;

    public function mount(Club $club): void
    {
        $this->club = $club;
    }

    public function post(): void
    {
        $this->validate([
            'body' => 'required|string|max:5000',
            'type' => 'required|in:discussion,review_share,activity',
            'book_id' => 'nullable|exists:books,id',
        ]);

        try {
            app(CommunityService::class)->createPost(auth()->user(), $this->club, [
                'body' => $this->body,
                'type' => $this->type,
                'book_id' => $this->book_id,
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset(['body', 'book_id']);
        $this->error = null;
    }

    public function deletePost(int $postId): void
    {
        $post = CommunityPost::findOrFail($postId);

        try {
            app(CommunityService::class)->deletePost(auth()->user(), $post);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function render()
    {
        return view('livewire.club-discussion', [
            'posts' => $this->club->posts()->with(['user', 'book'])
                ->where('status', CommunityPost::STATUS_PUBLISHED)
                ->latest()->paginate(20),
        ]);
    }
}
