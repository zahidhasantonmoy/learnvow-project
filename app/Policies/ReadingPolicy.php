<?php

namespace App\Policies;

use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use App\Models\ReaderSetting;
use App\Models\ReadingProgress;
use App\Models\User;

/**
 * Reader rows are strictly per-user (spec §1.4): a reader may only
 * view / change / delete their own progress, settings, bookmarks,
 * highlights and notes.
 *
 * Registration note for the coordinator: this one policy covers four
 * models, so the default policy-name guess does not find it — register
 * it explicitly, e.g. in AppServiceProvider::boot():
 *   Gate::policy(Bookmark::class, ReadingPolicy::class);
 *   Gate::policy(Highlight::class, ReadingPolicy::class);
 *   Gate::policy(Note::class, ReadingPolicy::class);
 *   Gate::policy(ReadingProgress::class, ReadingPolicy::class);
 *   Gate::policy(ReaderSetting::class, ReadingPolicy::class);
 * Controllers and Livewire components also enforce the same own-row
 * check inline, so access is safe even before that registration lands.
 */
class ReadingPolicy
{
    private function owns(User $user, int $ownerId): bool
    {
        return $user->id === $ownerId;
    }

    public function view(User $user, ReadingProgress|ReaderSetting|Bookmark|Highlight|Note $row): bool
    {
        return $this->owns($user, $row->user_id);
    }

    public function update(User $user, ReadingProgress|ReaderSetting|Bookmark|Highlight|Note $row): bool
    {
        return $this->owns($user, $row->user_id);
    }

    public function delete(User $user, ReadingProgress|ReaderSetting|Bookmark|Highlight|Note $row): bool
    {
        return $this->owns($user, $row->user_id);
    }
}
