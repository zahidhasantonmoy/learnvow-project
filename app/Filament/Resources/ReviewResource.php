<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReviewResource\Pages;
use App\Models\Report;
use App\Models\Review;
use App\Services\AdminService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema as SchemaFacade;

/**
 * Builder C — adminModeration (spec §4.4). Reviews are written by readers
 * (Builder B) and only moderated here: approve / reject / hide via
 * AdminService. No review creation or content editing in the panel.
 */
class ReviewResource extends BaseAdminResource
{
    protected static ?string $model = Review::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'title';

    /**
     * Role-audit Low-3 fix: Support may LIST and VIEW reviews (roles doc:
     * "review reported books/reviews") but never moderate them. Create and
     * edit stay admin-only via BaseAdminResource; delete stays Super Admin
     * only. The approve/reject/hide actions below are additionally hidden
     * from Support — AdminService::moderateReview already refuses them
     * (canModerateReviews is admin-only), so this only fixes the UI.
     */
    public static function getViewAnyAuthorizationResponse(): Response
    {
        $user = static::currentUser();

        return static::allowOrDeny(
            $user !== null && AdminService::userHasRole($user, ['support', 'admin', 'super-admin'])
        );
    }

    public static function getViewAuthorizationResponse(Model $record): Response
    {
        $user = static::currentUser();

        return static::allowOrDeny(
            $user !== null && AdminService::userHasRole($user, ['support', 'admin', 'super-admin'])
        );
    }

    public static function getModelLabel(): string
    {
        return __('admin.reviews.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.reviews.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.reviews.plural');
    }

    public static function statusOptions(): array
    {
        return collect(['pending', 'approved', 'rejected', 'hidden'])
            ->mapWithKeys(fn (string $s) => [$s => __('admin.status.'.$s)])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('rating')->label(__('admin.reviews.rating'))->disabled(),
            TextInput::make('title')->label(__('admin.reviews.title'))->disabled(),
            Textarea::make('body')->label(__('admin.reviews.body'))->disabled(),
            Select::make('status')
                ->label(__('admin.reviews.status'))
                ->options(static::statusOptions())
                ->disabled()
                ->dehydrated(false),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('user.name')->label(__('admin.reviews.user')),
            TextEntry::make('book.title')->label(__('admin.reviews.book')),
            TextEntry::make('rating')->label(__('admin.reviews.rating')),
            TextEntry::make('title')->label(__('admin.reviews.title')),
            TextEntry::make('body')->label(__('admin.reviews.body')),
            TextEntry::make('status')
                ->label(__('admin.reviews.status'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('admin.status.'.$state)),
        ]);
    }

    public static function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('book.title')
                ->label(__('admin.reviews.book'))
                ->searchable()
                ->sortable(),
            TextColumn::make('user.name')
                ->label(__('admin.reviews.user'))
                ->sortable(),
            TextColumn::make('rating')
                ->label(__('admin.reviews.rating'))
                ->sortable(),
            TextColumn::make('status')
                ->label(__('admin.reviews.status'))
                ->badge()
                ->color(fn (string $state) => $state === 'approved' ? 'success' : ($state === 'pending' ? 'warning' : 'danger'))
                ->formatStateUsing(fn (string $state) => __('admin.status.'.$state))
                ->sortable(),
            TextColumn::make('created_at')
                ->label(__('admin.reviews.created_at'))
                ->dateTime()
                ->sortable(),
        ];

        // Phase 3 (§5.1): reported reviews surface in the moderation
        // queue — open-report count per review.
        $filters = [
            SelectFilter::make('status')
                ->label(__('admin.reviews.status'))
                ->options(static::statusOptions()),
        ];

        if (SchemaFacade::hasTable('reports')) {
            array_splice($columns, 4, 0, [
                TextColumn::make('open_reports')
                    ->label(__('moderation.reports.reported_reviews'))
                    ->state(fn (Review $record) => Report::query()
                        ->where('reportable_type', Report::TYPE_REVIEW)
                        ->where('reportable_id', $record->getKey())
                        ->where('status', 'open')
                        ->count())
                    ->badge()
                    ->color(fn (Review $record) => Report::query()
                        ->where('reportable_type', Report::TYPE_REVIEW)
                        ->where('reportable_id', $record->getKey())
                        ->where('status', 'open')
                        ->exists() ? 'danger' : 'gray'),
            ]);

            $filters[] = Filter::make('reported_only')
                ->label(__('moderation.reports.reported_reviews'))
                ->query(fn (Builder $query) => $query->whereExists(function ($q) {
                    $q->selectRaw('1')->from('reports')
                        ->whereColumn('reports.reportable_id', 'reviews.id')
                        ->where('reports.reportable_type', Report::TYPE_REVIEW)
                        ->where('reports.status', 'open');
                }));
        }

        return $table
            ->columns($columns)
            ->filters($filters)
            ->actions([
                Action::make('approve')
                    ->label(__('admin.actions.approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Review $record) => AdminService::isAdmin(auth()->user()) && in_array($record->status, ['pending', 'hidden', 'rejected'], true))
                    ->action(fn (Review $record) => app(AdminService::class)->moderateReview($record, 'approved', auth()->user()))
                    ->successNotificationTitle(__('admin.actions.approved')),
                Action::make('reject')
                    ->label(__('admin.actions.reject'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Review $record) => AdminService::isAdmin(auth()->user()) && in_array($record->status, ['pending', 'approved', 'hidden'], true))
                    ->action(fn (Review $record) => app(AdminService::class)->moderateReview($record, 'rejected', auth()->user()))
                    ->successNotificationTitle(__('admin.actions.rejected')),
                Action::make('hide')
                    ->label(__('admin.actions.hide'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (Review $record) => AdminService::isAdmin(auth()->user()) && in_array($record->status, ['pending', 'approved'], true))
                    ->action(fn (Review $record) => app(AdminService::class)->moderateReview($record, 'hidden', auth()->user()))
                    ->successNotificationTitle(__('admin.actions.hidden')),
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReviews::route('/'),
            'view' => Pages\ViewReview::route('/{record}'),
        ];
    }
}
