<?php

namespace App\Policies;

use App\Models\ReviewReply;
use App\Models\User;

/**
 * Phase 3 (spec §1.8) — Builder A. A review reply belongs to the
 * author of the reviewed book: they may edit it, but never delete or
 * hide it — deletion is Admin/Super Admin (moderation) only.
 */
class ReviewReplyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(?User $user, ReviewReply $reply): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('author');
    }

    public function update(User $user, ReviewReply $reply): bool
    {
        return (int) $reply->user_id === (int) $user->id;
    }

    public function delete(User $user, ReviewReply $reply): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }
}
