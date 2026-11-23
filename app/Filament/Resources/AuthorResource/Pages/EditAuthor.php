<?php

namespace App\Filament\Resources\AuthorResource\Pages;

use App\Filament\Resources\AuthorResource;
use App\Models\Author;
use App\Notifications\AuthorNotification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;

class EditAuthor extends EditRecord
{
    protected static string $resource = AuthorResource::class;

    protected function afterSave(): void
    {
        // Spec §6 / §4.2: when the Super Admin changes the 80% share, every
        // approved author is told (in-app only, no money details beyond
        // the new share percent).
        if (! $this->record->wasChanged('revenue_share_pct')) {
            return;
        }

        if (! Schema::hasTable('notifications')) {
            return;
        }

        $sharePct = (int) $this->record->revenue_share_pct;

        Author::query()
            ->where('status', 'approved')
            ->whereNotNull('user_id')
            ->with('user')
            ->get()
            ->each(function (Author $author) use ($sharePct) {
                $author->user?->notify(new AuthorNotification('share_changed', [
                    'author_id' => $author->id,
                    'share_pct' => $sharePct,
                ]));
            });
    }
}
