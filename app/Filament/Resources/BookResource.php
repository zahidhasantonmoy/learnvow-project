<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Models\Book;
use App\Services\AdminService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Builder C — adminBooks (spec §4.4). Approve / reject via AdminService;
 * status is never edited freehand. Prices are Super Admin only (roles doc:
 * Admin cannot change prices).
 */
class BookResource extends BaseAdminResource
{
    protected static ?string $model = Book::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('admin.books.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.books.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.books.plural');
    }

    public static function statusOptions(): array
    {
        return collect(['draft', 'in_review', 'published', 'rejected', 'taken_down'])
            ->mapWithKeys(fn (string $s) => [$s => __('admin.status.'.$s)])
            ->all();
    }

    public static function statusColor(string $state): string
    {
        return match ($state) {
            'published' => 'success',
            'in_review' => 'warning',
            'rejected', 'taken_down' => 'danger',
            default => 'gray',
        };
    }

    public static function form(Schema $schema): Schema
    {
        $priceDisabled = ! AdminService::isSuperAdmin(auth()->user());

        return $schema->components([
            TextInput::make('title')
                ->label(__('admin.books.title'))
                ->required()
                ->maxLength(255),
            TextInput::make('title_en')
                ->label(__('admin.books.title_en'))
                ->maxLength(255),
            TextInput::make('slug')
                ->label(__('admin.books.slug'))
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Select::make('author_id')
                ->label(__('admin.books.author'))
                ->relationship('author', 'name')
                ->searchable()
                ->required(),
            Select::make('category_id')
                ->label(__('admin.books.category'))
                ->relationship('category', 'name')
                ->searchable()
                ->required(),
            Select::make('language')
                ->label(__('admin.books.language'))
                ->options(['bn' => 'বাংলা', 'en' => 'English'])
                ->required(),
            TextInput::make('price_bdt')
                ->label(__('admin.books.price'))
                ->numeric()
                ->minValue(0)
                ->required()
                ->disabled($priceDisabled),
            TextInput::make('list_price_bdt')
                ->label(__('admin.books.list_price'))
                ->numeric()
                ->minValue(0)
                ->disabled($priceDisabled),
            TextInput::make('pages')
                ->label(__('admin.books.pages'))
                ->numeric()
                ->minValue(1),
            Toggle::make('has_audiobook')
                ->label(__('admin.books.audiobook')),
            Toggle::make('is_free')
                ->label(__('admin.books.free')),
            Select::make('status')
                ->label(__('admin.books.status'))
                ->options(static::statusOptions())
                ->disabled()
                ->dehydrated(false),
            Textarea::make('description')
                ->label(__('admin.books.description'))
                ->rows(4),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('title')->label(__('admin.books.title')),
            TextEntry::make('author.name')->label(__('admin.books.author')),
            TextEntry::make('category.name')->label(__('admin.books.category')),
            TextEntry::make('price_bdt')
                ->label(__('admin.books.price'))
                ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state)),
            TextEntry::make('status')
                ->label(__('admin.books.status'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('admin.status.'.$state)),
            TextEntry::make('description')->label(__('admin.books.description')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.books.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label(__('admin.books.author'))
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label(__('admin.books.category'))
                    ->sortable(),
                TextColumn::make('price_bdt')
                    ->label(__('admin.books.price'))
                    ->formatStateUsing(fn ($state) => '৳'.number_format((int) $state))
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('admin.books.status'))
                    ->badge()
                    ->color(fn (string $state) => static::statusColor($state))
                    ->formatStateUsing(fn (string $state) => __('admin.status.'.$state))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.books.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('admin.books.status'))
                    ->options(static::statusOptions()),
                SelectFilter::make('category_id')
                    ->label(__('admin.books.category'))
                    ->relationship('category', 'name'),
            ])
            ->actions([
                Action::make('approve')
                    ->label(__('admin.actions.approve'))
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Book $record) => in_array($record->status, ['draft', 'in_review', 'rejected'], true))
                    ->requiresConfirmation()
                    ->action(fn (Book $record) => app(AdminService::class)->approveBook($record, auth()->user()))
                    ->successNotificationTitle(__('admin.actions.approved')),
                Action::make('reject')
                    ->label(__('admin.actions.reject'))
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (Book $record) => in_array($record->status, ['draft', 'in_review', 'published'], true))
                    ->form([
                        Textarea::make('reason')
                            ->label(__('admin.actions.reject'))
                            ->maxLength(500),
                    ])
                    ->action(fn (Book $record, array $data) => app(AdminService::class)->rejectBook($record, $data['reason'] ?? null, auth()->user()))
                    ->successNotificationTitle(__('admin.actions.rejected')),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn () => AdminService::isSuperAdmin(auth()->user())),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBooks::route('/'),
            'view' => Pages\ViewBook::route('/{record}'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
