<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PrintOrderResource\Pages;
use App\Models\PrintOrder;
use App\Models\User;
use App\Services\AdminService;
use App\Services\PrintService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Print order queue (Phase 4 spec §2.2, §14.7). Admin confirms the
 * TrxID-verified order, sends it to a partner (writing the partner
 * cost — spec A4), and marks delivery. Admin may NOT change prices —
 * only Super Admin sets print prices (Settings page); this resource
 * has no price editing at all.
 */
class PrintOrderResource extends BaseAdminResource
{
    protected static ?string $model = PrintOrder::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-printer';

    protected static ?int $navigationSort = 12;

    protected static ?string $recordTitleAttribute = 'order_no';

    public static function getModelLabel(): string
    {
        return __('admin.print_orders.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.print_orders.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.print_orders.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.manage');
    }

    public static function statusOptions(): array
    {
        return collect(PrintOrder::STATUSES)
            ->mapWithKeys(fn (string $s) => [$s => __('admin.print_orders.status_'.$s)])
            ->all();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('order_no')->label(__('admin.print_orders.order_no')),
            TextEntry::make('book.title')->label(__('admin.print_orders.book')),
            TextEntry::make('user.name')->label(__('admin.print_orders.buyer')),
            TextEntry::make('quantity')->label(__('admin.print_orders.quantity')),
            TextEntry::make('total_bdt')->label(__('admin.print_orders.total'))->money('BDT'),
            TextEntry::make('status')->label(__('admin.print_orders.status'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('admin.print_orders.status_'.$state)),
            TextEntry::make('partner.name')->label(__('admin.print_orders.partner'))->placeholder('—'),
            TextEntry::make('partner_cost_bdt')->label(__('admin.print_orders.partner_cost'))->placeholder('—'),
            TextEntry::make('payment_trx_id')->label(__('print.trx_id'))->placeholder('—'),
            TextEntry::make('tracking_no')->label(__('admin.print_orders.tracking_no'))->placeholder('—'),
            TextEntry::make('admin_note')->label(__('print.admin_note'))->placeholder('—'),
            TextEntry::make('created_at')->label(__('admin.print_orders.created_at'))->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_no')->label(__('admin.print_orders.order_no'))->searchable()->sortable(),
                TextColumn::make('book.title')->label(__('admin.print_orders.book')),
                TextColumn::make('user.name')->label(__('admin.print_orders.buyer')),
                TextColumn::make('quantity')->label(__('admin.print_orders.quantity')),
                TextColumn::make('total_bdt')->label(__('admin.print_orders.total'))->money('BDT'),
                TextColumn::make('status')->label(__('admin.print_orders.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin.print_orders.status_'.$state)),
                TextColumn::make('partner.name')->label(__('admin.print_orders.partner')),
                TextColumn::make('created_at')->label(__('admin.print_orders.created_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(static::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPrintOrders::route('/'),
            'view' => Pages\ViewPrintOrder::route('/{record}'),
        ];
    }
}
