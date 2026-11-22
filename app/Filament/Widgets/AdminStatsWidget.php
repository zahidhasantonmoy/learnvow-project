<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Schema;

/**
 * Builder C — admin dashboard stats (spec §4.4 adminDash shell).
 * Table guards keep the dashboard rendering before Builder A/B finish
 * their migrations.
 */
class AdminStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $booksPending = Schema::hasTable('books')
            ? \App\Models\Book::query()->where('status', 'in_review')->count()
            : 0;

        $authorsPending = Schema::hasTable('authors')
            ? \App\Models\Author::query()->where('status', 'pending')->count()
            : 0;

        $users = Schema::hasTable('users')
            ? \App\Models\User::query()->count()
            : 0;

        $reviewsPending = Schema::hasTable('reviews')
            ? \App\Models\Review::query()->where('status', 'pending')->count()
            : 0;

        return [
            Stat::make(__('admin.stats.books_pending'), $booksPending)
                ->icon('heroicon-o-book-open'),
            Stat::make(__('admin.stats.authors_pending'), $authorsPending)
                ->icon('heroicon-o-pencil-square'),
            Stat::make(__('admin.stats.users'), $users)
                ->icon('heroicon-o-users'),
            Stat::make(__('admin.stats.reviews_pending'), $reviewsPending)
                ->icon('heroicon-o-star'),
        ];
    }
}
