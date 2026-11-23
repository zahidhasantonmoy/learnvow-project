<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TakedownResource\Pages;
use App\Models\TakedownRequest;
use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Builder C — adminCopyright (spec §5.3). Takedown claims with the 48h
 * first-response TARGET (assumption A9) and the four Admin decisions:
 * take down / extend proof 7 days / reject / close (verified).
 */
class TakedownResource extends BaseAdminResource
{
    protected static ?string $model = TakedownRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?int $navigationSort = 9;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('moderation.takedowns.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('moderation.takedowns.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('moderation.takedowns.plural');
    }

    public static function statusOptions(): array
    {
        return collect(['open', 'awaiting_proof', 'action_taken', 'rejected', 'closed'])
            ->mapWithKeys(fn (string $s) => [$s => __('moderation.takedowns.status_'.$s)])
            ->all();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('book.title')->label(__('moderation.takedowns.book')),
            TextEntry::make('claimant_name')->label(__('moderation.takedowns.claimant_name')),
            TextEntry::make('claimant_email')->label(__('moderation.takedowns.claimant_email')),
            TextEntry::make('reason')->label(__('moderation.takedowns.reason')),
            TextEntry::make('proof_path')
                ->label(__('moderation.takedowns.proof_path'))
                ->placeholder('—'),
            TextEntry::make('status')
                ->label(__('moderation.takedowns.status'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('moderation.takedowns.status_'.$state)),
            TextEntry::make('deadline_at')
                ->label(__('moderation.takedowns.deadline'))
                ->dateTime()
                ->badge()
                ->color(fn (TakedownRequest $record) => $record->isOverdue() ? 'danger' : 'gray')
                ->formatStateUsing(fn (?string $state, TakedownRequest $record) => $state
                    .($record->isOverdue() ? ' — '.__('moderation.takedowns.deadline_overdue') : '')),
            TextEntry::make('reviewer.name')
                ->label(__('moderation.takedowns.reviewed_by'))
                ->placeholder('—'),
            TextEntry::make('decision_note')
                ->label(__('moderation.takedowns.decision_note'))
                ->placeholder('—'),
            TextEntry::make('created_at')
                ->label(__('moderation.takedowns.created_at'))
                ->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('book.title')
                    ->label(__('moderation.takedowns.book'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('claimant_name')
                    ->label(__('moderation.takedowns.claimant_name'))
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('moderation.takedowns.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'danger',
                        'awaiting_proof' => 'warning',
                        'action_taken', 'closed' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __('moderation.takedowns.status_'.$state))
                    ->sortable(),
                TextColumn::make('deadline_at')
                    ->label(__('moderation.takedowns.deadline'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('moderation.takedowns.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('moderation.takedowns.status'))
                    ->options(static::statusOptions()),
            ])
            ->actions([
                ViewAction::make(),
            ]);
    }

    /** One decision action per outcome; all go through AdminService::decideTakedown. */
    public static function decisionAction(string $decision, string $labelKey, string $color): Action
    {
        return Action::make($decision)
            ->label(__($labelKey))
            ->color($color)
            ->form([
                Textarea::make('note')
                    ->label(__('moderation.takedowns.decision_note'))
                    ->rows(3)
                    ->required(fn () => in_array($decision, ['reject', 'close'], true)),
            ])
            ->action(fn (TakedownRequest $record, array $data) => app(AdminService::class)
                ->decideTakedown($record, $decision, auth()->user(), $data['note'] ?? null))
            ->successNotificationTitle(__('moderation.takedowns.decided'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTakedowns::route('/'),
            'view' => Pages\ViewTakedown::route('/{record}'),
        ];
    }
}
