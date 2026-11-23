<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\AdminService;
use App\Services\BillingService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Builder C — adminOrders + adminOrderDetail (spec §3.6, §6.2, §6.3).
 *
 * Full order detail: items, buyer, TrxID (via payments), timeline
 * (payment_events), refund request. Refund approval is SUPER ADMIN ONLY
 * (roles doc, spec §3.6); an approved refund goes to the user's WALLET
 * (type=refund), never cash — the money movement lives in
 * BillingService::refundOrder() (Builder B), never in this file.
 * Admin may only mark a request "under review".
 *
 * Records are service-managed: Admin can never create/edit/delete an
 * order by hand.
 */
class OrderResource extends BaseAdminResource
{
    protected static ?string $model = Order::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'order_no';

    public static function getModelLabel(): string
    {
        return __('admin.orders.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.orders.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.orders.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.manage');
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function getEditAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAuthorizationResponse(Model $record): Response
    {
        return Response::deny();
    }

    public static function getDeleteAnyAuthorizationResponse(): Response
    {
        return Response::deny();
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'paid' => 'success',
            'awaiting_verification', 'pending_payment' => 'warning',
            'failed', 'duplicate_suspect', 'cancelled' => 'danger',
            'refunded' => 'info',
            default => 'gray',
        };
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('order_no')
                ->label(__('admin.orders.order_no')),
            TextEntry::make('user.name')
                ->label(__('admin.orders.buyer')),
            TextEntry::make('user.phone')
                ->label(__('admin.users.phone')),
            TextEntry::make('type')
                ->label(__('admin.orders.type'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('admin.orders.type_'.$state)),
            TextEntry::make('status')
                ->label(__('admin.orders.status'))
                ->badge()
                ->color(fn (string $state) => static::statusColor($state))
                ->formatStateUsing(fn (string $state) => __('admin.orders.status_'.$state)),
            TextEntry::make('subtotal_bdt')
                ->label(__('admin.orders.subtotal'))
                ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state)),
            TextEntry::make('coupon_amt_bdt')
                ->label(__('admin.orders.coupon_amt'))
                ->formatStateUsing(fn ($state) => '−৳'.number_format((int) $state)),
            TextEntry::make('wallet_used_bdt')
                ->label(__('admin.orders.wallet_used'))
                ->formatStateUsing(fn ($state) => '−৳'.number_format((int) $state)),
            TextEntry::make('total_bdt')
                ->label(__('admin.orders.total'))
                ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state)),
            RepeatableEntry::make('items')
                ->label(__('admin.orders.items'))
                ->state(function (Order $record) {
                    // order_items (spec §6.3). Relation name is Builder B's;
                    // try the conventional names, fall back to an empty list.
                    $items = null;
                    foreach (['items', 'orderItems'] as $relation) {
                        if (method_exists($record, $relation)) {
                            $items = $record->{$relation};
                            break;
                        }
                    }

                    return ($items ?? collect())->map(fn ($item) => [
                        'title' => $item->title_snapshot,
                        'price' => '৳'.number_format((int) $item->unit_price_bdt)
                            .($item->list_price_bdt ? ' (list ৳'.number_format((int) $item->list_price_bdt).')' : '')
                            .' × '.$item->quantity,
                    ])->all();
                })
                ->schema([
                    TextEntry::make('title')->label(__('admin.orders.item_title')),
                    TextEntry::make('price')->label(__('admin.orders.item_price')),
                ]),
            TextEntry::make('payments')
                ->label(__('admin.orders.trxids'))
                ->state(function (Order $record) {
                    $payments = null;
                    foreach (['payments', 'payment'] as $relation) {
                        if (method_exists($record, $relation)) {
                            $payments = $record->{$relation};
                            break;
                        }
                    }
                    $payments = $payments instanceof \Illuminate\Database\Eloquent\Collection
                        ? $payments
                        : collect($payments ? [$payments] : []);

                    return $payments->map(fn ($p) => $p->trx_id.' ('.__('admin.payments.method_'.$p->method).', '.__('admin.payments.status_'.$p->status).', ৳'.number_format((int) $p->amount_bdt).')')
                        ->implode("\n") ?: '—';
                }),
            TextEntry::make('refund_status')
                ->label(__('admin.orders.refund_status'))
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    'approved' => 'success',
                    'requested', 'under_review' => 'warning',
                    'rejected' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state) => __('admin.orders.refund_'.$state)),
            TextEntry::make('refund_requested_at')
                ->label(__('admin.orders.refund_requested_at'))
                ->dateTime(),
            TextEntry::make('refund_note')
                ->label(__('admin.orders.refund_note'))
                ->state(fn (Order $record) => __('admin.orders.refund_under_review_note'))
                ->visible(fn (Order $record) => in_array($record->refund_status, ['requested', 'under_review'], true)),
            TextEntry::make('timeline')
                ->label(__('admin.orders.timeline'))
                ->state(function (Order $record) {
                    if (! class_exists(\App\Models\PaymentEvent::class)) {
                        return '—';
                    }

                    return \App\Models\PaymentEvent::query()
                        ->where('order_id', $record->getKey())
                        ->orderBy('created_at')
                        ->get()
                        ->map(fn ($e) => $e->created_at->format('Y-m-d H:i').' — '.$e->action
                            .($e->note ? ': '.$e->note : ''))
                        ->implode("\n") ?: '—';
                }),
            TextEntry::make('created_at')
                ->label(__('admin.orders.created_at'))
                ->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('order_no')
                    ->label(__('admin.orders.order_no'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('admin.orders.buyer'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('admin.orders.type'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin.orders.type_'.$state)),
                TextColumn::make('status')
                    ->label(__('admin.orders.status'))
                    ->badge()
                    ->color(fn (string $state) => static::statusColor($state))
                    ->formatStateUsing(fn (string $state) => __('admin.orders.status_'.$state))
                    ->sortable(),
                TextColumn::make('total_bdt')
                    ->label(__('admin.orders.total'))
                    ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state))
                    ->sortable(),
                TextColumn::make('refund_status')
                    ->label(__('admin.orders.refund_status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin.orders.refund_'.$state)),
                TextColumn::make('created_at')
                    ->label(__('admin.orders.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.orders.status'))
                    ->options([
                        'pending_payment' => __('admin.orders.status_pending_payment'),
                        'awaiting_verification' => __('admin.orders.status_awaiting_verification'),
                        'paid' => __('admin.orders.status_paid'),
                        'failed' => __('admin.orders.status_failed'),
                        'duplicate_suspect' => __('admin.orders.status_duplicate_suspect'),
                        'refunded' => __('admin.orders.status_refunded'),
                        'cancelled' => __('admin.orders.status_cancelled'),
                    ]),
                SelectFilter::make('type')
                    ->label(__('admin.orders.type'))
                    ->options([
                        'book' => __('admin.orders.type_book'),
                        'premium' => __('admin.orders.type_premium'),
                        'gift' => __('admin.orders.type_gift'),
                        'wallet_topup' => __('admin.orders.type_wallet_topup'),
                    ]),
                SelectFilter::make('refund_status')
                    ->label(__('admin.orders.refund_status'))
                    ->options([
                        'requested' => __('admin.orders.refund_requested'),
                        'under_review' => __('admin.orders.refund_under_review'),
                        'approved' => __('admin.orders.refund_approved'),
                        'rejected' => __('admin.orders.refund_rejected'),
                    ]),
            ])
            ->actions([
                // Refund queue (spec §3.4): Admin may take a request under
                // review; approve / reject is SUPER ADMIN ONLY.
                Action::make('mark_under_review')
                    ->label(__('admin.orders.mark_under_review'))
                    ->icon('heroicon-o-eye')
                    ->visible(fn (Order $record) => $record->refund_status === 'requested'
                        && AdminService::isAdmin(auth()->user()))
                    ->action(fn (Order $record) => app(AdminService::class)
                        ->markRefundUnderReview($record, auth()->user()))
                    ->successNotificationTitle(__('admin.orders.marked_under_review')),
                Action::make('approve_refund')
                    ->label(__('admin.orders.approve_refund'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Order $record) => in_array($record->refund_status, ['requested', 'under_review'], true)
                        && AdminService::isSuperAdmin(auth()->user()))
                    ->action(function (Order $record) {
                        app(BillingService::class)->refundOrder($record, auth()->user());
                        AdminService::writeAudit(auth()->user(), 'refund.approved', 'orders', $record->getKey(), [
                            'message' => 'Refund approved: '.$record->order_no,
                        ]);
                    })
                    ->successNotificationTitle(__('admin.orders.refund_approved')),
                Action::make('reject_refund')
                    ->label(__('admin.orders.reject_refund'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Order $record) => in_array($record->refund_status, ['requested', 'under_review'], true)
                        && AdminService::isSuperAdmin(auth()->user()))
                    ->form([
                        Textarea::make('note')->label(__('admin.orders.refund_note'))->rows(2),
                    ])
                    ->action(fn (Order $record, array $data) => app(AdminService::class)
                        ->rejectRefund($record, auth()->user(), $data['note'] ?? null))
                    ->successNotificationTitle(__('admin.orders.refund_rejected')),
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
