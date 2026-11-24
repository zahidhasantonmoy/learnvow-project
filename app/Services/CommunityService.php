<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\CommunityPost;
use App\Models\Follow;
use App\Models\ReadingProgress;
use App\Models\Report;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Community / clubs (Phase 4 spec §6).
 *
 * Feed (§6.1) is an activity feed only — no user-written feed posts.
 * Discussion lives in clubs (§6.2). No pre-moderation (spec A15):
 * posts are hidden only after a report, by Support; Admin may
 * restore or delete. Private-club invites are NOT in Phase 4 (A15).
 */
class CommunityService
{
    /** Create a club; the creator becomes its first member (role creator). */
    public function createClub(User $user, array $data): Club
    {
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            throw new InvalidArgumentException('Club name is required.');
        }

        return DB::transaction(function () use ($user, $data, $name) {
            $club = Club::create([
                'name' => $name,
                'description' => $data['description'] ?? null,
                'creator_id' => $user->id,
                'is_public' => (bool) ($data['is_public'] ?? true),
                'reading_goal' => $data['reading_goal'] ?? null,
            ]);

            ClubMember::create([
                'club_id' => $club->id, 'user_id' => $user->id,
                'role' => ClubMember::ROLE_CREATOR, 'joined_at' => now(),
            ]);

            // The creator joins their own club — the club_joiner badge
            // is idempotent, so awarding here is safe (Phase 4 §4.5).
            app(BadgeService::class)->checkAndAward($user, 'club_joined');

            return $club->fresh();
        });
    }

    /** Join a public club (private clubs need an invite — Phase 4: no invites). */
    public function joinClub(User $user, Club $club): ClubMember
    {
        if (! $club->is_public) {
            throw new InvalidArgumentException('This is a private club — joining needs an invitation.');
        }

        if ($club->hasMember($user)) {
            throw new InvalidArgumentException('You are already a member of this club.');
        }

        if ($club->isFull()) {
            throw new InvalidArgumentException('This club is full (500 members).');
        }

        $member = ClubMember::create([
            'club_id' => $club->id, 'user_id' => $user->id,
            'role' => ClubMember::ROLE_MEMBER, 'joined_at' => now(),
        ]);

        // Badge hook (Phase 4 §4.5): joining a club awards club_joiner.
        app(BadgeService::class)->checkAndAward($user, 'club_joined');

        return $member;
    }

    public function leaveClub(User $user, Club $club): void
    {
        if ((int) $club->creator_id === (int) $user->id) {
            throw new InvalidArgumentException('The club creator cannot leave their own club.');
        }

        ClubMember::where('club_id', $club->id)->where('user_id', $user->id)->delete();
    }

    /** Member post inside a club (text + optional book link). */
    public function createPost(User $user, Club $club, array $data): CommunityPost
    {
        if (! $club->hasMember($user)) {
            throw new InvalidArgumentException('Only club members can post in a club.');
        }

        $body = trim((string) ($data['body'] ?? ''));

        if ($body === '') {
            throw new InvalidArgumentException('Post text is required.');
        }

        $type = (string) ($data['type'] ?? CommunityPost::TYPE_DISCUSSION);

        if (! in_array($type, CommunityPost::TYPES, true)) {
            throw new InvalidArgumentException('Invalid post type.');
        }

        return CommunityPost::create([
            'club_id' => $club->id,
            'user_id' => $user->id,
            'type' => $type,
            'body' => $body,
            'book_id' => $data['book_id'] ?? null,
            'status' => CommunityPost::STATUS_PUBLISHED,
        ]);
    }

    /** Edit own post. */
    public function updatePost(User $user, CommunityPost $post, string $body): CommunityPost
    {
        if ((int) $post->user_id !== (int) $user->id) {
            throw new InvalidArgumentException('You can only edit your own posts.');
        }

        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('Post text is required.');
        }

        $post->update(['body' => $body]);

        return $post->fresh();
    }

    /**
     * Delete a post: own post, club moderator, or Admin/Support (§6.2).
     */
    public function deletePost(User $user, CommunityPost $post): void
    {
        if (! $this->canModeratePost($user, $post)) {
            throw new InvalidArgumentException('You cannot delete this post.');
        }

        $post->delete();
    }

    /** Support hides a reported post (§6.2); Admin may hide too. */
    public function hidePost(User $user, CommunityPost $post): CommunityPost
    {
        if (! $user->hasAnyRole(['support', 'admin', 'super-admin'])) {
            throw new InvalidArgumentException('Only Support or Admin can hide a post.');
        }

        $post->update(['status' => CommunityPost::STATUS_HIDDEN]);

        return $post->fresh();
    }

    /** Admin restores a hidden post. */
    public function restorePost(User $user, CommunityPost $post): CommunityPost
    {
        if (! $user->hasAnyRole(['admin', 'super-admin'])) {
            throw new InvalidArgumentException('Only Admin can restore a hidden post.');
        }

        $post->update(['status' => CommunityPost::STATUS_PUBLISHED]);

        return $post->fresh();
    }

    public function canModeratePost(User $user, CommunityPost $post): bool
    {
        if ((int) $post->user_id === (int) $user->id) {
            return true;
        }

        if ($user->hasAnyRole(['admin', 'super-admin', 'support'])) {
            return true;
        }

        $membership = ClubMember::where('club_id', $post->club_id)
            ->where('user_id', $user->id)->first();

        return $membership !== null && $membership->isModerator();
    }

    /** Report a post into the existing `reports` table (spec §6.2). */
    public function reportPost(User $user, CommunityPost $post, string $reason, ?string $details = null): Report
    {
        return Report::create([
            'reporter_id' => $user->id,
            'reportable_type' => 'community_posts',
            'reportable_id' => $post->id,
            'reason' => $reason,
            'details' => $details,
            'status' => 'open',
        ]);
    }

    /**
     * Activity feed (§6.1): finished books + approved reviews of users
     * I follow, my clubs' published posts, and announcements. Private
     * shelves / private goals never appear (they are not read here).
     * Followed users are authors (Phase 1 `follows`: user → author).
     */
    public function feedFor(User $user, int $limit = 30): array
    {
        $followedAuthorIds = Follow::where('user_id', $user->id)->pluck('author_id');
        $followedUserIds = \App\Models\Author::whereIn('id', $followedAuthorIds)
            ->whereNotNull('user_id')->pluck('user_id');

        $myClubIds = ClubMember::where('user_id', $user->id)->pluck('club_id');

        $finished = ReadingProgress::with(['user', 'book'])
            ->whereIn('user_id', $followedUserIds)
            ->whereNotNull('finished_at')
            ->latest('finished_at')->limit($limit)->get()
            ->map(fn ($row) => [
                'kind' => 'finished', 'user' => $row->user, 'book' => $row->book,
                'at' => $row->finished_at,
            ]);

        $reviews = Review::with(['user', 'book'])->approved()
            ->whereIn('user_id', $followedUserIds)
            ->latest()->limit($limit)->get()
            ->map(fn ($row) => [
                'kind' => 'review', 'user' => $row->user, 'book' => $row->book,
                'review' => $row, 'at' => $row->created_at,
            ]);

        $posts = CommunityPost::with(['user', 'book', 'club'])
            ->whereIn('club_id', $myClubIds)
            ->where('status', CommunityPost::STATUS_PUBLISHED)
            ->latest()->limit($limit)->get()
            ->map(fn ($row) => [
                'kind' => 'club_post', 'user' => $row->user, 'book' => $row->book,
                'post' => $row, 'club' => $row->club, 'at' => $row->created_at,
            ]);

        $announcements = Announcement::latest()->limit(5)->get()
            ->map(fn ($row) => ['kind' => 'announcement', 'announcement' => $row, 'at' => $row->created_at]);

        // The mapped items are plain PHP arrays, not Eloquent models —
        // Eloquent Collection::merge() calls getKey() on its items and
        // 500s on arrays. Drop to base collections first, then concat.
        $items = $finished->toBase()->merge($reviews->toBase())
            ->merge($posts->toBase())->merge($announcements->toBase())
            ->sortByDesc(fn ($item) => $item['at'] ?? now())
            ->take($limit)->values()->all();

        return $items;
    }

    /** Public clubs + clubs I belong to, for the clubs index. */
    public function clubsFor(User $user)
    {
        $myClubIds = ClubMember::where('user_id', $user->id)->pluck('club_id');

        return Club::where('is_public', true)
            ->orWhereIn('id', $myClubIds)
            ->withCount('members')->latest()->get();
    }
}
