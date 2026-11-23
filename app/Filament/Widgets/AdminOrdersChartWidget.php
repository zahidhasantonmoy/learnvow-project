<?php

namespace App\Filament\Widgets;

use App\Services\AdminService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Builder C — adminDash (spec §3.1). Last-7-days orders bar chart,
 * computed LIVE from `orders` — never hardcoded. Today's bar must match
 * today's order count.
 */
class AdminOrdersChartWidget extends ChartWidget
{
    protected ?string $heading = null;

    /**
     * Round 2 minor: order/payment data is Admin / Super Admin only.
     * Support sees only the "Open support tickets" stat (same scoping
     * as AdminStatsWidget in round 1) — never this chart.
     */
    public static function canView(): bool
    {
        return AdminService::isAdmin(auth()->user());
    }

    public function getHeading(): string
    {
        return __('admin.stats.orders_last_7_days');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $labels = [];
        $counts = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i);
            $labels[] = $day->format('d M');
            $counts[] = 0;
        }

        if (Schema::hasTable('orders')) {
            $since = now()->subDays(6)->startOfDay();

            $rows = DB::table('orders')
                ->selectRaw('date(created_at) as day, COUNT(*) as c')
                ->where('created_at', '>=', $since)
                ->groupBy('day')
                ->pluck('c', 'day');

            foreach (range(0, 6) as $offset) {
                $day = now()->subDays(6 - $offset)->format('Y-m-d');
                $counts[$offset] = (int) ($rows[$day] ?? 0);
            }
        }

        return [
            'datasets' => [
                [
                    'label' => __('admin.stats.orders_count'),
                    'data' => $counts,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
