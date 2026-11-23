<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use App\Models\Report;
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
 * Builder C — adminModeration reports (spec §5.2). Support may HIDE a
 * reported book (or review) until Admin reviews; only Admin decides —
 * dismiss (book visible again, hidden_at NULL) or action (book
 * taken_down / review rejected). Every decision is audited.
 */
class ReportResource extends BaseAdminResource
{
    protected static ?string $model = Report::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'id';

    protected static function resourceAllowed(): bool
    {
        $user = static::currentUser();

        return $user !== null && AdminService::userHasRole($user, ['support', 'admin', 'super-admin']);
    }

    public static function getModelLabel(): string
    {
        return __('moderation.reports.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('moderation.reports.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('moderation.reports.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.manage');
    }

    public static function statusOptions(): array
    {
        return collect(['open', 'reviewing', 'action_taken', 'dismissed'])
            ->mapWithKeys(fn (string $s) => [$s => __('moderation.reports.status_'.$s)])
            ->all();
    }

    public static function reasonOptions(): array
    {
        return collect(['copyright', 'spam', 'abuse', 'wrong_content', 'other'])
            ->mapWithKeys(fn (string $s) => [$s => __('moderation.reports.reason_'.$s)])
            ->all();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('reporter.name')->label(__('moderation.reports.reporter')),
            TextEntry::make('reportable_type')
                ->label(__('moderation.reports.reported'))
                ->state(function (Report $record) {
                    $target = $record->reportable();

                    if ($target === null) {
                        return $record->reportable_type.' #'.$record->reportable_id;
                    }

                    return $record->reportable_type.' — '.($target->title ?? ('#'.$target->getKey()));
                }),
            TextEntry::make('reason')
                ->label(__('moderation.reports.reason'))
                ->formatStateUsing(fn (string $state) => __('moderation.reports.reason_'.$state)),
            TextEntry::make('details')
                ->label(__('moderation.reports.details'))
                ->placeholder('—'),
            TextEntry::make('status')
                ->label(__('moderation.reports.status'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('moderation.reports.status_'.$state)),
            TextEntry::make('reviewer.name')
                ->label(__('moderation.reports.reviewed_by'))
                ->placeholder('—'),
            TextEntry::make('review_note')
                ->label(__('moderation.reports.review_note'))
                ->placeholder('—'),
            TextEntry::make('created_at')
                ->label(__('moderation.reports.created_at'))
                ->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reporter.name')
                    ->label(__('moderation.reports.reporter'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('reportable_type')
                    ->label(__('moderation.reports.reported'))
                    ->formatStateUsing(fn (Report $record) => $record->reportable_type.' #'.$record->reportable_id),
                TextColumn::make('reason')
                    ->label(__('moderation.reports.reason'))
                    ->formatStateUsing(fn (string $state) => __('moderation.reports.reason_'.$state)),
                TextColumn::make('status')
                    ->label(__('moderation.reports.status'))
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'danger',
                        'reviewing' => 'warning',
                        'action_taken' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state) => __('moderation.reports.status_'.$state))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('moderation.reports.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('moderation.reports.status'))
                    ->options(static::statusOptions()),
                SelectFilter::make('reason')
                    ->label(__('moderation.reports.reason'))
                    ->options(static::reasonOptions()),
            ])
            ->actions([
                Action::make('hide_book')
                    ->label(__('moderation.reports.hide_book'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (Report $record) => $record->reportable_type === Report::TYPE_BOOK
                        && in_array($record->status, ['open', 'reviewing'], true))
                    ->action(function (Report $record) {
                        $book = $record->book;
                        if ($book) {
                            app(AdminService::class)->hideBook($book, auth()->user(), 'Report #'.$record->id);
                        }
                        $record->status = 'reviewing';
                        $record->save();
                    })
                    ->successNotificationTitle(__('moderation.reports.book_hidden')),
                Action::make('hide_review')
                    ->label(__('moderation.reports.hide_review'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (Report $record) => $record->reportable_type === Report::TYPE_REVIEW
                        && in_array($record->status, ['open', 'reviewing'], true))
                    ->action(function (Report $record) {
                        $review = $record->review;
                        if ($review && $review->status === 'approved') {
                            app(AdminService::class)->moderateReview($review, 'hidden', auth()->user());
                        }
                        $record->status = 'reviewing';
                        $record->save();
                    })
                    ->successNotificationTitle(__('admin.actions.hidden')),
                ViewAction::make(),
            ]);
    }

    /** Admin decision actions (dismiss / action) — shared by list + view. */
    public static function decideAction(string $decision, string $labelKey, string $color): Action
    {
        return Action::make('decide_'.$decision)
            ->label(__($labelKey))
            ->color($color)
            ->visible(fn (Report $record) => in_array($record->status, ['open', 'reviewing'], true)
                && AdminService::isAdmin(auth()->user()))
            ->form([
                Textarea::make('note')
                    ->label(__('moderation.reports.review_note'))
                    ->rows(3),
            ])
            ->action(fn (Report $record, array $data) => app(AdminService::class)
                ->decideReport($record, $decision, auth()->user(), $data['note'] ?? null))
            ->successNotificationTitle($decision === 'dismiss'
                ? __('moderation.reports.dismissed')
                : __('moderation.reports.action_taken'));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'view' => Pages\ViewReport::route('/{record}'),
        ];
    }
}
