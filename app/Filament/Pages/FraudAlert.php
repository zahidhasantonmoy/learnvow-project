<?php

namespace App\Filament\Pages;

use App\Models\FraudAlert as FraudAlertModel;
use App\Services\AdminService;
use App\Services\BillingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Builder C — adminFraud (spec §3.3, §6.6).
 *
 * Duplicate-TrxID list + decision + note. No access is granted until a
 * decision is made; the duplicate_suspect payment stays blocked. The
 * decision itself goes through BillingService::decideFraud() (Builder B),
 * which records decided_by and writes the payment_events audit row.
 */
class FraudAlert extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static ?string $slug = 'fraud-alerts';

    protected static ?int $navigationSort = 14;

    public static function getNavigationLabel(): string
    {
        return __('admin.fraud.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.manage');
    }

    public function getTitle(): string
    {
        return __('admin.fraud.heading');
    }

    public static function canAccess(): bool
    {
        return AdminService::isAdmin(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(FraudAlertModel::query())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('trx_id_normalized')
                    ->label(__('admin.fraud.trx_id'))
                    ->searchable()
                    ->copyable(),
                TextColumn::make('payment.order.order_no')
                    ->label(__('admin.orders.order_no')),
                TextColumn::make('payment.user.name')
                    ->label(__('admin.fraud.new_payment_user')),
                TextColumn::make('existingPayment.order.order_no')
                    ->label(__('admin.fraud.existing_order')),
                TextColumn::make('existingPayment.user.name')
                    ->label(__('admin.fraud.existing_payment_user')),
                TextColumn::make('payment.amount_bdt')
                    ->label(__('admin.payments.amount'))
                    ->formatStateUsing(fn ($state) => $state === null ? '—' : '৳'.number_format((int) $state)),
                TextColumn::make('status')
                    ->label(__('admin.fraud.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'warning',
                        'confirmed_fraud' => 'danger',
                        'mistake', 'legitimate' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __('admin.fraud.status_'.$state))
                    ->sortable(),
                TextColumn::make('decidedBy.name')
                    ->label(__('admin.fraud.decided_by')),
                TextColumn::make('created_at')
                    ->label(__('admin.fraud.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.fraud.status'))
                    ->options([
                        'open' => __('admin.fraud.status_open'),
                        'confirmed_fraud' => __('admin.fraud.status_confirmed_fraud'),
                        'mistake' => __('admin.fraud.status_mistake'),
                        'legitimate' => __('admin.fraud.status_legitimate'),
                    ]),
            ])
            ->recordActions([
                Action::make('decide')
                    ->label(__('admin.fraud.decide'))
                    ->icon('heroicon-o-scale')
                    ->color('warning')
                    ->visible(fn (FraudAlertModel $record) => $record->status === 'open')
                    ->form([
                        Select::make('decision')
                            ->label(__('admin.fraud.decision'))
                            ->options([
                                'confirmed_fraud' => __('admin.fraud.status_confirmed_fraud'),
                                'mistake' => __('admin.fraud.status_mistake'),
                                'legitimate' => __('admin.fraud.status_legitimate'),
                            ])
                            ->required(),
                        Textarea::make('decision_note')
                            ->label(__('admin.fraud.decision_note'))
                            ->required()
                            ->maxLength(1000)
                            ->rows(3),
                    ])
                    ->action(function (FraudAlertModel $record, array $data) {
                        app(BillingService::class)->decideFraud(
                            $record,
                            auth()->user(),
                            $data['decision'],
                            $data['decision_note']
                        );

                        Notification::make()
                            ->title(__('admin.fraud.decided'))
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
