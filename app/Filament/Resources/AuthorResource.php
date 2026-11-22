<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuthorResource\Pages;
use App\Models\Author;
use App\Services\AdminService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Builder C — adminAuthors (spec §4.4). Approve / reject via AdminService;
 * approval grants the `author` add-on role to the linked account.
 * The 80% revenue share is Super Admin only (roles doc).
 */
class AuthorResource extends BaseAdminResource
{
    protected static ?string $model = Author::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-pencil-square';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.authors.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.authors.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.authors.plural');
    }

    public static function statusOptions(): array
    {
        return collect(['pending', 'approved', 'rejected', 'suspended'])
            ->mapWithKeys(fn (string $s) => [$s => __('admin.status.'.$s)])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.authors.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('pen_name')
                ->label(__('admin.authors.pen_name'))
                ->maxLength(255),
            TextInput::make('slug')
                ->label(__('admin.authors.slug'))
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Select::make('type')
                ->label(__('admin.authors.type'))
                ->options(['author' => 'Author', 'publisher' => 'Publisher'])
                ->required(),
            Select::make('user_id')
                ->label(__('admin.authors.user'))
                ->relationship('user', 'name')
                ->searchable()
                ->nullable(),
            TextInput::make('revenue_share_pct')
                ->label(__('admin.authors.revenue_share'))
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->disabled(! AdminService::isSuperAdmin(auth()->user())),
            TextInput::make('bkash_number')
                ->label(__('admin.authors.bkash'))
                ->maxLength(20),
            Select::make('status')
                ->label(__('admin.authors.status'))
                ->options(static::statusOptions())
                ->disabled()
                ->dehydrated(false),
            Textarea::make('bio')
                ->label(__('admin.authors.bio'))
                ->rows(4),
            Textarea::make('bio_en')
                ->label(__('admin.authors.bio').' (English)')
                ->rows(4),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label(__('admin.authors.name')),
            TextEntry::make('type')->label(__('admin.authors.type')),
            TextEntry::make('status')
                ->label(__('admin.authors.status'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('admin.status.'.$state)),
            TextEntry::make('bio')->label(__('admin.authors.bio')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.authors.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('admin.authors.type'))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.authors.status'))
                    ->badge()
                    ->color(fn (string $state) => $state === 'approved' ? 'success' : ($state === 'pending' ? 'warning' : 'danger'))
                    ->formatStateUsing(fn (string $state) => __('admin.status.'.$state))
                    ->sortable(),
                TextColumn::make('books_count')
                    ->label(__('admin.authors.books_count'))
                    ->counts('books')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.books.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.authors.status'))
                    ->options(static::statusOptions()),
            ])
            ->actions([
                Action::make('approve')
                    ->label(__('admin.actions.approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Author $record) => in_array($record->status, ['pending', 'rejected'], true))
                    ->requiresConfirmation()
                    ->action(fn (Author $record) => app(AdminService::class)->approveAuthor($record, auth()->user()))
                    ->successNotificationTitle(__('admin.actions.approved')),
                Action::make('reject')
                    ->label(__('admin.actions.reject'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Author $record) => in_array($record->status, ['pending', 'approved'], true))
                    ->form([
                        Textarea::make('reason')->label(__('admin.actions.reject'))->maxLength(500),
                    ])
                    ->action(fn (Author $record, array $data) => app(AdminService::class)->rejectAuthor($record, $data['reason'] ?? null, auth()->user()))
                    ->successNotificationTitle(__('admin.actions.rejected')),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuthors::route('/'),
            'create' => Pages\CreateAuthor::route('/create'),
            'view' => Pages\ViewAuthor::route('/{record}'),
            'edit' => Pages\EditAuthor::route('/{record}/edit'),
        ];
    }
}
