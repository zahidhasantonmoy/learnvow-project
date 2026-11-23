<?php

namespace App\Filament\Widgets;

use App\Models\SupportTicket;
use App\Services\AdminService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builder C — admin dashboard stats (spec §4.4 adminDash shell, §3.1).
 * Phase 3: the full live work queue — every count comes from the DB,
 * never hardcoded. Admin sees only their own queues (payout/settings
 * rows are Super Admin-only and never appear here).
 * Table guards keep the dashboard rendering before Builder A/B finish
 * their migrations.
 */
class AdminStatsWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $count = fn (string $table, array $where = []) => Schema::hasTable($table)
            ? DB::table($table)->where($where)->count()
            : 0;

        // M5 (spec §3.1 / roles doc): stats are scoped by role — the pages
        // already 403 correctly, but the numbers themselves must not leak.
        // Super Admin sees everything; Admin sees no payout/settings/price
        // stats; Support sees only ticket-related stats.
        $user = auth()->user();

        if ($user !== null && AdminService::userHasRole($user, 'support')
            && ! AdminService::isAdmin($user)) {
            return [
                Stat::make(__('admin.stats.support_open'), Schema::hasTable('support_tickets')
                    ? SupportTicket::query()->whereIn('status', ['open', 'in_progress', 'escalated'])->count()
                    : 0)
                    ->icon('heroicon-o-chat-bubble-left-right'),
            ];
        }

        $stats = [
            Stat::make(__('admin.stats.books_pending'), $count('books', ['status' => 'in_review']))
                ->icon('heroicon-o-book-open'),
            Stat::make(__('admin.stats.authors_pending'), $count('authors', ['status' => 'pending']))
                ->icon('heroicon-o-pencil-square'),
            Stat::make(__('admin.stats.users'), $count('users'))
                ->icon('heroicon-o-users'),
            Stat::make(__('admin.stats.reviews_pending'), $count('reviews', ['status' => 'pending']))
                ->icon('heroicon-o-star'),
            Stat::make(__('admin.stats.payments_pending'), $count('payments', ['status' => 'awaiting_verification']))
                ->icon('heroicon-o-credit-card'),
            Stat::make(__('admin.stats.fraud_open'), $count('fraud_alerts', ['status' => 'open']))
                ->icon('heroicon-o-shield-exclamation'),
            Stat::make(__('admin.stats.orders_count'), $count('orders'))
                ->icon('heroicon-o-shopping-bag'),
            Stat::make(__('admin.stats.support_open'), Schema::hasTable('support_tickets')
                ? SupportTicket::query()->whereIn('status', ['open', 'in_progress', 'escalated'])->count()
                : 0)
                ->icon('heroicon-o-chat-bubble-left-right'),
            Stat::make(__('admin.stats.subscriptions_active'), $count('subscriptions', ['status' => 'active']))
                ->icon('heroicon-o-sparkles'),
            Stat::make(__('admin.stats.reports_open'), $count('reports', ['status' => 'open']))
                ->icon('heroicon-o-flag'),
            Stat::make(__('admin.stats.takedowns_open'), $count('takedown_requests', ['status' => 'open']))
                ->icon('heroicon-o-scale'),
        ];

        // Payout queue is money — Super Admin only.
        if (AdminService::isSuperAdmin($user)) {
            $stats[] = Stat::make(__('admin.stats.payouts_pending'), $count('author_payouts', ['status' => 'pending']))
                ->icon('heroicon-o-banknotes');
        }

        return $stats;
    }
}
