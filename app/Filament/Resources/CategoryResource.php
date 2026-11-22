<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Category;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Builder C — adminContent, category part (spec §4.4). Banners are Phase 3.
 */
class CategoryResource extends BaseAdminResource
{
    protected static ?string $model = Category::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.categories.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.categories.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.categories.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.categories.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('name_en')
                ->label(__('admin.categories.name_en'))
                ->required()
                ->maxLength(255),
            TextInput::make('slug')
                ->label(__('admin.categories.slug'))
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            Textarea::make('description')
                ->label(__('admin.categories.description'))
                ->rows(3),
            Textarea::make('description_en')
                ->label(__('admin.categories.description').' (English)')
                ->rows(3),
            TextInput::make('sort_order')
                ->label(__('admin.categories.sort_order'))
                ->numeric()
                ->default(0),
            Toggle::make('is_active')
                ->label(__('admin.categories.active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.categories.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_en')
                    ->label(__('admin.categories.name_en'))
                    ->searchable(),
                TextColumn::make('slug')
                    ->label(__('admin.categories.slug')),
                TextColumn::make('books_count')
                    ->label(__('admin.authors.books_count'))
                    ->counts('books')
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label(__('admin.categories.sort_order'))
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label(__('admin.categories.active'))
                    ->boolean(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCategories::route('/'),
        ];
    }
}
