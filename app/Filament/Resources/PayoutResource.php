<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PayoutResource\Pages;
use App\Models\AuthorPayout;
use App\Services\AdminService;
use App\Services\PayoutService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;

/**
 * Builder C — superPayouts (spec §4.4). SUPER ADMIN ONLY — Admin gets 403
 * even by direct URL (resource gate + PayoutService::requireSuperAdmin).
 * Approve / release (with the bKash TrxID the Super Admin sent outside
 * the system) / fail / retry all go through PayoutService. The list
 * shows the MASKED destination only — never a full bKash number.
 */
class PayoutResource extends BaseAdminResource
{
    protected static bool $superAdminOnly = true;

    protected static ?string $model = AuthorPayout::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?int $navigationSort = 21;

    protected static ?string $recordTitleAttribute = 'period';

    public static function getModelLabel(): string
    {
        return __('admin.payouts.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.payouts.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.payouts.plural');
    }

    public static function getCreateAuthorizationResponse(): Response
    {
        return Response::deny(); // Payouts are created by closePeriod(), never by hand.
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

    public static function statusOptions(): array
    {
        return collect(['pending', 'approved', 'paid', 'failed'])
            ->mapWithKeys(fn (string $s) => [$s => __('admin.payouts.status_'.$s)])
            ->all();
    }

    /** Live month figures (spec §4.4, A18): real data only, never the model figures. */
    public static function monthFigures(?string $period = null): array
    {
        $period ??= now()->format('Y-m');
        $figures = ['collected' => 0, 'pool' => 0, 'platform' => 0, 'pending' => 0];

        if (! SchemaFacade::hasTable('author_payouts')) {
            return $figures;
        }

        $figures['pending'] = (int) AuthorPayout::query()
            ->where('status', 'pending')
            ->sum('total_bdt');

        if (SchemaFacade::hasTable('orders')) {
            $collected = (int) DB::table('orders')
                ->where('status', 'paid')
                ->whereRaw("strftime('%Y-%m', created_at) = ?", [$period])
                ->sum('total_bdt');
            $figures['collected'] = $collected;
            $figures['pool'] = (int) floor($collected * 0.8);
            $figures['platform'] = $collected - $figures['pool'];
        }

        return $figures;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('author.name')->label(__('admin.payouts.author')),
            TextEntry::make('period')->label(__('admin.payouts.period')),
            TextEntry::make('sales_bdt')
                ->label(__('admin.payouts.sales'))
                ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state)),
            TextEntry::make('pool_bdt')
                ->label(__('admin.payouts.pool'))
                ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state)),
            TextEntry::make('total_bdt')
                ->label(__('admin.payouts.total'))
                ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state)),
            // M3: the author's computed available balance (earnings − paid
            // payouts, spec §2.5) sits next to the payout amount, so the
            // Super Admin sees at a glance whether this release exceeds it.
            TextEntry::make('available_balance')
                ->label(__('admin.payouts.available_balance'))
                ->state(fn (AuthorPayout $record) => $record->author !== null
                    ? '৳'.number_format(app(PayoutService::class)->authorBalance($record->author))
                    : '—'),
            TextEntry::make('method')->label(__('admin.payouts.method')),
            TextEntry::make('masked_destination')
                ->label(__('admin.payouts.destination'))
                ->state(fn (AuthorPayout $record) => ($record->method === 'bkash' ? 'bKash ' : 'Bank ')
                    .($record->maskedDestination() ?? '—')),
            TextEntry::make('status')
                ->label(__('admin.payouts.status'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('admin.payouts.status_'.$state)),
            TextEntry::make('payout_trx_id')
                ->label(__('admin.payouts.trx_id'))
                ->placeholder('—'),
            TextEntry::make('approver.name')
                ->label(__('admin.payouts.approved_by'))
                ->placeholder('—'),
            TextEntry::make('approved_at')
                ->label(__('admin.payouts.approved_at'))
                ->dateTime(),
            TextEntry::make('paid_at')
                ->label(__('admin.payouts.paid_at'))
                ->dateTime(),
            TextEntry::make('failure_reason')
                ->label(__('admin.payouts.failure_reason'))
                ->placeholder('—')
                ->visible(fn (AuthorPayout $record) => $record->status === 'failed'),
            TextEntry::make('retry_count')
                ->label(__('admin.payouts.retry_count')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('author.name')
                    ->label(__('admin.payouts.author'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period')
                    ->label(__('admin.payouts.period'))
                    ->sortable(),
                TextColumn::make('total_bdt')
                    ->label(__('admin.payouts.total'))
                    ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state))
                    ->sortable(),
                TextColumn::make('method')
                    ->label(__('admin.payouts.method')),
                TextColumn::make('masked_destination')
                    ->label(__('admin.payouts.destination'))
                    ->state(fn (AuthorPayout $record) => $record->maskedDestination() ?? '—'),
                TextColumn::make('status')
                    ->label(__('admin.payouts.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'approved' => 'info',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state) => __('admin.payouts.status_'.$state))
                    ->sortable(),
                TextColumn::make('retry_count')
                    ->label(__('admin.payouts.retry_count')),
                TextColumn::make('created_at')
                    ->label(__('admin.payouts.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.payouts.status'))
                    ->options(static::statusOptions()),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('admin.actions.approve_payout'))
            ->icon('heroicon-o-check')
            ->color('success')
            ->visible(fn (AuthorPayout $record) => $record->status === 'pending'
                && AdminService::isSuperAdmin(auth()->user()))
            ->action(function (AuthorPayout $record) {
                app(PayoutService::class)->approvePayout($record, auth()->user());
                AdminService::writeAudit(auth()->user(), 'payout.approved', 'author_payouts', $record->getKey(), [
                    'message' => 'Approved author payout ৳'.number_format((int) $record->total_bdt),
                ]);
            })
            ->successNotificationTitle(__('admin.actions.payout_approved'));
    }

    public static function releaseAction(): Action
    {
        return Action::make('release')
            ->label(__('admin.actions.release_payout'))
            ->icon('heroicon-o-paper-airplane')
            ->color('primary')
            ->visible(fn (AuthorPayout $record) => $record->status === 'approved'
                && AdminService::isSuperAdmin(auth()->user()))
            // M3: the modal always shows the author's available balance;
            // when the payout exceeds it, the warning + confirmation is
            // the explicit override — the Super Admin may still proceed.
            ->requiresConfirmation()
            ->modalDescription(function (AuthorPayout $record) {
                if ($record->author === null) {
                    return null;
                }

                $balance = app(PayoutService::class)->authorBalance($record->author);
                $total = (int) $record->total_bdt;

                if ($total > $balance) {
                    return __('admin.payouts.exceeds_balance_warning', [
                        'total' => '৳'.number_format($total),
                        'balance' => '৳'.number_format($balance),
                    ]);
                }

                return __('admin.payouts.available_balance').': ৳'.number_format($balance);
            })
            ->form([
                TextInput::make('payout_trx_id')
                    ->label(__('admin.payouts.trx_id'))
                    ->required()
                    ->maxLength(50),
            ])
            ->action(function (AuthorPayout $record, array $data) {
                $payouts = app(PayoutService::class);
                $balance = $record->author !== null
                    ? $payouts->authorBalance($record->author)
                    : null;
                $exceeds = $balance !== null && (int) $record->total_bdt > $balance;

                $payouts->releasePayout($record, auth()->user(), $data['payout_trx_id']);
                AdminService::writeAudit(auth()->user(), 'payout.released', 'author_payouts', $record->getKey(), [
                    'message' => 'Released author payout ৳'.number_format((int) $record->total_bdt),
                    // M3: an over-balance release is an audited override.
                    'available_balance_bdt' => $balance,
                    'balance_override' => $exceeds,
                ]);
            })
            ->successNotificationTitle(__('admin.actions.payout_released'));
    }

    public static function failAction(): Action
    {
        return Action::make('fail')
            ->label(__('admin.actions.payout_failed'))
            ->icon('heroicon-o-exclamation-triangle')
            ->color('danger')
            ->visible(fn (AuthorPayout $record) => in_array($record->status, ['pending', 'approved'], true)
                && AdminService::isSuperAdmin(auth()->user()))
            ->form([
                Textarea::make('reason')
                    ->label(__('admin.payouts.failure_reason'))
                    ->required()
                    ->rows(3),
            ])
            ->action(function (AuthorPayout $record, array $data) {
                app(PayoutService::class)->failPayout($record, auth()->user(), $data['reason']);
            })
            ->successNotificationTitle(__('admin.actions.payout_failed'));
    }

    public static function retryAction(): Action
    {
        return Action::make('retry')
            ->label(__('admin.actions.payout_retried'))
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (AuthorPayout $record) => $record->status === 'failed'
                && AdminService::isSuperAdmin(auth()->user()))
            ->action(function (AuthorPayout $record) {
                app(PayoutService::class)->retryFailedPayout($record, auth()->user());
            })
            ->successNotificationTitle(__('admin.actions.payout_retried'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPayouts::route('/'),
            'view' => Pages\ViewPayout::route('/{record}'),
        ];
    }
}
