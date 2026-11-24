<?php

namespace App\Services;

use App\Models\Badge;
use App\Models\ReadingProgress;
use App\Models\User;
use App\Models\UserBadge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Event-based badge awarding (§4.5). Called from ReaderService
 * (saveProgress / finish), GoalTracker (goal completed) and
 * CommunityService (club join — Builder C). One badge once only
 * (unique user_id+badge_id); missing badge rows are skipped, never
 * created — definitions come only from BadgeSeeder.
 */
class BadgeService
{
    /**
     * Evaluate the badges tied to $event and award the ones earned.
     * Returns the slugs awarded in this call.
     *
     * Events: book_finished, progress_saved, goal_completed, club_joined.
     */
    public function checkAndAward(User $user, string $event): array
    {
        if (! Schema::hasTable('badges') || ! Schema::hasTable('user_badges')) {
            return [];
        }

        $candidates = match ($event) {
            'book_finished' => ['first_book', 'books_10', 'books_50', 'night_owl'],
            'progress_saved' => ['streak_7', 'streak_30'],
            'goal_completed' => ['goal_completed'],
            'club_joined' => ['club_joiner'],
            default => [],
        };

        $awarded = [];

        foreach ($candidates as $slug) {
            if ($this->earns($user, $slug) && $this->award($user, $slug)) {
                $awarded[] = $slug;
            }
        }

        return $awarded;
    }

    /** Award one badge (idempotent). True when newly awarded. */
    public function award(User $user, string $slug): bool
    {
        $badge = Badge::where('slug', $slug)->first();

        if ($badge === null) {
            return false;
        }

        try {
            $row = UserBadge::firstOrCreate(
                ['user_id' => $user->id, 'badge_id' => $badge->id],
                ['awarded_at' => now()]
            );

            return $row->wasRecentlyCreated;
        } catch (\Throwable) {
            // Race on the unique key — the other call awarded it.
            return false;
        }
    }

    public function hasBadge(User $user, string $slug): bool
    {
        return UserBadge::where('user_id', $user->id)
            ->whereHas('badge', fn ($q) => $q->where('slug', $slug))
            ->exists();
    }

    protected function earns(User $user, string $slug): bool
    {
        return match ($slug) {
            'first_book' => $this->finishedCount($user) >= 1,
            'books_10' => $this->finishedCount($user) >= 10,
            'books_50' => $this->finishedCount($user) >= 50,
            'streak_7' => $this->readingStreakDays($user) >= 7,
            'streak_30' => $this->readingStreakDays($user) >= 30,
            'night_owl' => $this->finishedAtNight($user),
            'club_joiner', 'goal_completed' => true,
            default => false,
        };
    }

    protected function finishedCount(User $user): int
    {
        return ReadingProgress::where('user_id', $user->id)
            ->whereNotNull('finished_at')
            ->count();
    }

    /** Latest finished book completed between 00:00 and 04:00 local time. */
    protected function finishedAtNight(User $user): bool
    {
        $latest = ReadingProgress::where('user_id', $user->id)
            ->whereNotNull('finished_at')
            ->orderByDesc('finished_at')
            ->first();

        return $latest !== null && (int) $latest->finished_at->format('H') < 4;
    }

    /**
     * Consecutive days read, ending today or yesterday — from
     * last_read_at dates on reading_progress.
     */
    public function readingStreakDays(User $user): int
    {
        $days = ReadingProgress::where('user_id', $user->id)
            ->whereNotNull('last_read_at')
            ->pluck('last_read_at')
            ->map(fn ($d) => $d->toDateString())
            ->unique()
            ->flip()
            ->all();

        if ($days === []) {
            return 0;
        }

        $streak = 0;
        $cursor = now()->toDateString();

        // A streak may end yesterday (not read yet today).
        if (! isset($days[$cursor])) {
            $cursor = now()->subDay()->toDateString();
            if (! isset($days[$cursor])) {
                return 0;
            }
        }

        while (isset($days[$cursor])) {
            $streak++;
            $cursor = date('Y-m-d', strtotime($cursor.' -1 day'));
        }

        return $streak;
    }
}
