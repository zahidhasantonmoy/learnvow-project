<?php

namespace App\Filament\Pages;

use App\Models\OrderItem;
use App\Services\AdminService;
use Filament\Forms\Components\Placeholder;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Support\HtmlString;

/**
 * Builder C — superAnalytics (spec §4.4). LIVE figures only, computed
 * from orders / order_items / users — the prototype's modelled figures
 * (subscription ৳186,000, book sales ৳98,400, collected ৳322,500…)
 * are NEVER seeded or shown (assumption A18).
 */
class Analytics extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $slug = 'analytics';

    protected static ?int $navigationSort = 25;

    public static function getNavigationLabel(): string
    {
        return __('admin.analytics.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.super_admin');
    }

    public function getTitle(): string
    {
        return __('admin.analytics.heading');
    }

    public static function canAccess(): bool
    {
        return AdminService::isSuperAdmin(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** Live figures for the last 30 days. */
    public static function figures(): array
    {
        $since = now()->subDays(30);
        $figures = [
            'subscriptions_revenue' => 0,
            'book_sales_revenue' => 0,
            'total_revenue' => 0,
            'new_readers_30d' => 0,
            'top_books' => [],
        ];

        if (SchemaFacade::hasTable('orders')) {
            $figures['subscriptions_revenue'] = (int) DB::table('orders')
                ->where('type', 'premium')
                ->where('status', 'paid')
                ->where('created_at', '>=', $since)
                ->sum('total_bdt');

            $figures['book_sales_revenue'] = (int) DB::table('orders')
                ->whereIn('type', ['book', 'gift'])
                ->where('status', 'paid')
                ->where('created_at', '>=', $since)
                ->sum('total_bdt');

            $figures['total_revenue'] = $figures['subscriptions_revenue'] + $figures['book_sales_revenue'];
        }

        if (SchemaFacade::hasTable('users')) {
            $figures['new_readers_30d'] = (int) DB::table('users')
                ->where('created_at', '>=', $since)
                ->count();
        }

        if (SchemaFacade::hasTable('order_items') && SchemaFacade::hasTable('orders')) {
            $rows = OrderItem::query()
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', 'paid')
                ->where('orders.created_at', '>=', $since)
                ->whereNotNull('order_items.book_id')
                ->selectRaw('order_items.book_id, SUM(order_items.quantity) as copies, SUM(order_items.unit_price_bdt * order_items.quantity) as revenue')
                ->groupBy('order_items.book_id')
                ->orderByDesc('revenue')
                ->limit(10)
                ->get();

            $figures['top_books'] = $rows->map(fn ($row) => [
                'title' => $row->book?->title ?? ('#'.$row->book_id),
                'copies' => (int) $row->copies,
                'revenue' => (int) $row->revenue,
            ])->all();
        }

        return $figures;
    }

    public function content(Schema $schema): Schema
    {
        $f = static::figures();

        $topBooks = empty($f['top_books'])
            ? '<p>—</p>'
            : '<table style="width:100%;border-collapse:collapse">'
                .'<tr><th style="text-align:left">'.e(__('admin.analytics.book')).'</th>'
                .'<th style="text-align:right">'.e(__('admin.analytics.copies')).'</th>'
                .'<th style="text-align:right">'.e(__('admin.analytics.revenue')).'</th></tr>'
                .collect($f['top_books'])->map(fn (array $b) => '<tr><td>'.e($b['title']).'</td>'
                    .'<td style="text-align:right">'.number_format($b['copies']).'</td>'
                    .'<td style="text-align:right">৳'.number_format($b['revenue']).'</td></tr>')->implode('')
                .'</table>';

        return $schema->components([
            Placeholder::make('note')
                ->content(__('admin.analytics.note')),
            Placeholder::make('subscriptions_revenue')
                ->label(__('admin.analytics.subscriptions_revenue').' ('.__('admin.analytics.last_30_days').')')
                ->content('৳'.number_format($f['subscriptions_revenue'])),
            Placeholder::make('book_sales_revenue')
                ->label(__('admin.analytics.book_sales_revenue').' ('.__('admin.analytics.last_30_days').')')
                ->content('৳'.number_format($f['book_sales_revenue'])),
            Placeholder::make('total_revenue')
                ->label(__('admin.analytics.total_revenue').' ('.__('admin.analytics.last_30_days').')')
                ->content('৳'.number_format($f['total_revenue'])),
            Placeholder::make('new_readers')
                ->label(__('admin.analytics.new_readers_30d'))
                ->content((string) $f['new_readers_30d']),
            Placeholder::make('top_books')
                ->label(__('admin.analytics.top_books'))
                ->content(new HtmlString($topBooks)),
        ]);
    }
}
