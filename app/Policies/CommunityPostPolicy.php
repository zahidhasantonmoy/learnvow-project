<?php

namespace App\Policies;

use App\Models\ClubMember;
use App\Models\CommunityPost;
use App\Models\User;

/** Community posts: members read; own post / moderator / staff delete. */
class CommunityPostPolicy
{
    public function view(?User $user, CommunityPost $post): bool
    {
        if ($post->status === CommunityPost::STATUS_PUBLISHED) {
            return true;
        }

        return $user !== null && $user->hasAnyRole(['admin', 'super-admin', 'support']);
    }

    public function create(User $user, CommunityPost $post): bool
    {
        return $post->club->hasMember($user);
    }

    public function update(User $user, CommunityPost $post): bool
    {
        return (int) $post->user_id === (int) $user->id;
    }

    public function delete(User $user, CommunityPost $post): bool
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

    /** Support hides reported posts; Admin hides too. */
    public function hide(User $user, CommunityPost $post): bool
    {
        return $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }
}
