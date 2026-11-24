<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VocabularyWord;

/** Own vocabulary words only. */
class VocabularyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VocabularyWord $word): bool
    {
        return $word->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, VocabularyWord $word): bool
    {
        return $word->user_id === $user->id;
    }

    public function delete(User $user, VocabularyWord $word): bool
    {
        return $word->user_id === $user->id;
    }
}
