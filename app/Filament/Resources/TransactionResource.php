<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\WalletTransaction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Builder C — adminTransactions (spec §3.6, §6.13): the wallet ledger —
 * all money in/out in one place. Read-only: ledger rows are written by
 * BillingService inside DB transactions, never by hand, and never edited
 * or deleted (ledger integrity: every row carries balance_after_bdt).
 */
class TransactionResource extends BaseAdminResource
{
    protected static ?string $model = WalletTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?int $navigationSort = 12;

    public static function getModelLabel(): string
    {
        return __('admin.transactions.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.transactions.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.transactions.plural');
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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('user.name')
                ->label(__('admin.transactions.user')),
            TextEntry::make('user.phone')
                ->label(__('admin.users.phone')),
            TextEntry::make('type')
                ->label(__('admin.transactions.type'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('admin.transactions.type_'.$state)),
            TextEntry::make('amount_bdt')
                ->label(__('admin.transactions.amount'))
                ->formatStateUsing(fn ($state) => ((int) $state >= 0 ? '+' : '−').'৳'.number_format(abs((int) $state))),
            TextEntry::make('balance_after_bdt')
                ->label(__('admin.transactions.balance_after'))
                ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state)),
            TextEntry::make('reference_type')
                ->label(__('admin.transactions.reference')),
            TextEntry::make('reference_id')
                ->label(__('admin.transactions.reference_id')),
            TextEntry::make('note')
                ->label(__('admin.transactions.note')),
            TextEntry::make('created_at')
                ->label(__('admin.transactions.created_at'))
                ->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('admin.transactions.user'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('admin.transactions.type'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'topup', 'refund', 'referral_credit' => 'success',
                        'purchase' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __('admin.transactions.type_'.$state)),
                TextColumn::make('amount_bdt')
                    ->label(__('admin.transactions.amount'))
                    ->formatStateUsing(fn ($state) => ((int) $state >= 0 ? '+' : '−').'৳'.number_format(abs((int) $state)))
                    ->sortable(),
                TextColumn::make('balance_after_bdt')
                    ->label(__('admin.transactions.balance_after'))
                    ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state))
                    ->sortable(),
                TextColumn::make('note')
                    ->label(__('admin.transactions.note'))
                    ->limit(60),
                TextColumn::make('created_at')
                    ->label(__('admin.transactions.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('admin.transactions.type'))
                    ->options([
                        'topup' => __('admin.transactions.type_topup'),
                        'purchase' => __('admin.transactions.type_purchase'),
                        'refund' => __('admin.transactions.type_refund'),
                        'referral_credit' => __('admin.transactions.type_referral_credit'),
                        'points_redeem' => __('admin.transactions.type_points_redeem'),
                        'adjustment' => __('admin.transactions.type_adjustment'),
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions::route('/'),
            'view' => Pages\ViewTransaction::route('/{record}'),
        ];
    }
}
