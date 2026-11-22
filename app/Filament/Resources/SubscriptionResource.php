<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionResource\Pages;
use App\Models\Subscription;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * Builder C — adminSubscriptions (spec §3.6, §6.7): trial, renewal,
 * expiry — all subscriptions. Read-only: subscriptions are created and
 * moved through their state machine by BillingService only (trial once
 * per account, manual renewal, cancel runs to end of term, no refund).
 */
class SubscriptionResource extends BaseAdminResource
{
    protected static ?string $model = Subscription::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?int $navigationSort = 13;

    public static function getModelLabel(): string
    {
        return __('admin.subscriptions.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.subscriptions.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.subscriptions.plural');
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
                ->label(__('admin.subscriptions.user')),
            TextEntry::make('user.phone')
                ->label(__('admin.users.phone')),
            TextEntry::make('plan.name')
                ->label(__('admin.subscriptions.plan')),
            TextEntry::make('order.order_no')
                ->label(__('admin.orders.order_no')),
            TextEntry::make('status')
                ->label(__('admin.subscriptions.status'))
                ->badge()
                ->color(fn (string $state) => match ($state) {
                    'active', 'trialing' => 'success',
                    'cancelled' => 'gray',
                    'expired', 'past_due' => 'danger',
                    default => 'gray',
                })
                ->formatStateUsing(fn (string $state) => __('admin.subscriptions.status_'.$state)),
            TextEntry::make('starts_at')
                ->label(__('admin.subscriptions.starts_at'))
                ->dateTime(),
            TextEntry::make('ends_at')
                ->label(__('admin.subscriptions.ends_at'))
                ->dateTime(),
            TextEntry::make('trial_ends_at')
                ->label(__('admin.subscriptions.trial_ends_at'))
                ->dateTime(),
            TextEntry::make('cancelled_at')
                ->label(__('admin.subscriptions.cancelled_at'))
                ->dateTime(),
            TextEntry::make('cancel_reason')
                ->label(__('admin.subscriptions.cancel_reason')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('ends_at', 'asc')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('admin.subscriptions.user'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('plan.name')
                    ->label(__('admin.subscriptions.plan'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('admin.subscriptions.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active', 'trialing' => 'success',
                        'cancelled' => 'gray',
                        'expired', 'past_due' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __('admin.subscriptions.status_'.$state))
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label(__('admin.subscriptions.starts_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label(__('admin.subscriptions.ends_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('trial_ends_at')
                    ->label(__('admin.subscriptions.trial_ends_at'))
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.subscriptions.status'))
                    ->options([
                        'trialing' => __('admin.subscriptions.status_trialing'),
                        'active' => __('admin.subscriptions.status_active'),
                        'cancelled' => __('admin.subscriptions.status_cancelled'),
                        'expired' => __('admin.subscriptions.status_expired'),
                        'past_due' => __('admin.subscriptions.status_past_due'),
                    ]),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSubscriptions::route('/'),
            'view' => Pages\ViewSubscription::route('/{record}'),
        ];
    }
}
