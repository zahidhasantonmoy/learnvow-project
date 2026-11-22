<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PaymentResource\Pages;
use App\Models\Payment;
use App\Services\AdminService;
use App\Services\BillingService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;

/**
 * Builder C — adminPayments (spec §3.6, §6.4).
 *
 * Admin verifies TrxIDs here. Verification NEVER grants access from this
 * file — it calls BillingService::verifyPayment() (Builder B), which grants
 * access and writes the payment_events audit row. Rejection likewise goes
 * through BillingService::rejectPayment().
 *
 * SLA (spec §3.1, A9): 12h is a GOAL, never auto-approve. The list shows
 * the age since submission and a "delayed" badge after the SLA hours
 * (settings key payment.sla_hours, default 12).
 *
 * Records are service-managed: Admin can never create/edit/delete a
 * payment by hand — only verify / reject via the service.
 */
class PaymentResource extends BaseAdminResource
{
    protected static ?string $model = Payment::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'trx_id';

    public static function getModelLabel(): string
    {
        return __('admin.payments.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.payments.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.payments.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.manage');
    }

    /** SLA hours for the delayed badge (settings key payment.sla_hours, default 12). */
    public static function slaHours(): int
    {
        if (SchemaFacade::hasTable('settings')) {
            $value = \Illuminate\Support\Facades\DB::table('settings')
                ->where('key', 'payment.sla_hours')
                ->value('value');

            if ($value !== null && is_numeric($value) && (int) $value > 0) {
                return (int) $value;
            }
        }

        return 12;
    }

    public static function isDelayed(Payment $payment): bool
    {
        return $payment->status === 'awaiting_verification'
            && $payment->submitted_at !== null
            && $payment->submitted_at->diffInHours(now()) >= static::slaHours();
    }

    // Records are created by checkout (BillingService), never by hand.
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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('admin_note')
                ->label(__('admin.payments.admin_note'))
                ->rows(3)
                ->maxLength(1000),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('order.order_no')
                ->label(__('admin.orders.order_no')),
            TextEntry::make('user.name')
                ->label(__('admin.payments.user')),
            TextEntry::make('user.phone')
                ->label(__('admin.users.phone')),
            TextEntry::make('method')
                ->label(__('admin.payments.method'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('admin.payments.method_'.$state)),
            TextEntry::make('trx_id')
                ->label(__('admin.payments.trx_id')),
            TextEntry::make('amount_bdt')
                ->label(__('admin.payments.amount'))
                ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state)),
            TextEntry::make('status')
                ->label(__('admin.payments.status'))
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    'verified' => 'success',
                    'rejected' => 'danger',
                    'duplicate_suspect' => 'warning',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state) => __('admin.payments.status_'.$state)),
            TextEntry::make('submitted_at')
                ->label(__('admin.payments.submitted_at'))
                ->dateTime(),
            TextEntry::make('age')
                ->label(__('admin.payments.age'))
                ->state(fn (Payment $record) => $record->submitted_at?->diffForHumans() ?? '—')
                ->badge()
                ->color(fn (Payment $record) => static::isDelayed($record) ? 'danger' : 'gray')
                ->formatStateUsing(fn (Payment $record) => static::isDelayed($record)
                    ? __('admin.payments.delayed')
                    : __('admin.payments.on_time')),
            TextEntry::make('verified_by')
                ->label(__('admin.payments.verified_by'))
                ->state(fn (Payment $record) => $record->verifier?->name ?? '—'),
            TextEntry::make('verified_at')
                ->label(__('admin.payments.verified_at'))
                ->dateTime(),
            TextEntry::make('fix_count')
                ->label(__('admin.payments.fix_count')),
            TextEntry::make('admin_note')
                ->label(__('admin.payments.admin_note')),
            TextEntry::make('events')
                ->label(__('admin.payments.timeline'))
                ->state(function (Payment $record) {
                    // payment_events (spec §6.5) — read via the model when
                    // Builder B has landed it; empty (not fatal) before that.
                    if (! class_exists(\App\Models\PaymentEvent::class)) {
                        return '—';
                    }

                    return \App\Models\PaymentEvent::query()
                        ->where('payment_id', $record->getKey())
                        ->orderBy('created_at')
                        ->get()
                        ->map(fn ($e) => $e->created_at->format('Y-m-d H:i').' — '.$e->action
                            .($e->note ? ': '.$e->note : ''))
                        ->implode("\n");
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'asc') // Oldest first — SLA queue.
            ->columns([
                TextColumn::make('order.order_no')
                    ->label(__('admin.orders.order_no'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('admin.payments.user'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.phone')
                    ->label(__('admin.users.phone'))
                    ->searchable(),
                TextColumn::make('method')
                    ->label(__('admin.payments.method'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin.payments.method_'.$state)),
                TextColumn::make('trx_id')
                    ->label(__('admin.payments.trx_id'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('amount_bdt')
                    ->label(__('admin.payments.amount'))
                    ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.payments.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'verified' => 'success',
                        'rejected' => 'danger',
                        'duplicate_suspect' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __('admin.payments.status_'.$state))
                    ->sortable(),
                TextColumn::make('submitted_at')
                    ->label(__('admin.payments.submitted_at'))
                    ->since()
                    ->sortable(),
                TextColumn::make('sla')
                    ->label(__('admin.payments.age'))
                    ->state(fn (Payment $record) => static::isDelayed($record) ? 'delayed' : 'on_time')
                    ->badge()
                    ->color(fn (string $state) => $state === 'delayed' ? 'danger' : 'success')
                    ->formatStateUsing(fn (string $state) => __('admin.payments.'.$state)),
                TextColumn::make('fix_count')
                    ->label(__('admin.payments.fix_count'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.payments.status'))
                    ->options([
                        'awaiting_verification' => __('admin.payments.status_awaiting_verification'),
                        'verified' => __('admin.payments.status_verified'),
                        'rejected' => __('admin.payments.status_rejected'),
                        'duplicate_suspect' => __('admin.payments.status_duplicate_suspect'),
                    ]),
                SelectFilter::make('method')
                    ->label(__('admin.payments.method'))
                    ->options([
                        'bkash' => __('admin.payments.method_bkash'),
                        'nagad' => __('admin.payments.method_nagad'),
                        'wallet' => __('admin.payments.method_wallet'),
                    ]),
            ])
            ->actions([
                Action::make('verify')
                    ->label(__('admin.payments.verify'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Payment $record) => $record->status === 'awaiting_verification')
                    ->requiresConfirmation()
                    ->action(fn (Payment $record) => app(BillingService::class)->verifyPayment($record, auth()->user()))
                    ->successNotificationTitle(__('admin.payments.verified')),
                Action::make('reject')
                    ->label(__('admin.payments.reject'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->status === 'awaiting_verification')
                    ->form([
                        Textarea::make('admin_note')
                            ->label(__('admin.payments.admin_note'))
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->action(fn (Payment $record, array $data) => app(BillingService::class)->rejectPayment($record, auth()->user(), $data['admin_note']))
                    ->successNotificationTitle(__('admin.payments.rejected')),
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayments::route('/'),
            'view' => Pages\ViewPayment::route('/{record}'),
        ];
    }
}
