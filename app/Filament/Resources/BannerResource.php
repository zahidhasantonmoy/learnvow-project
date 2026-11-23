<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BannerResource\Pages;
use App\Models\Banner;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Builder C — adminContent banners (spec §3.3). Home banners; the image
 * is a PUBLIC image via plain Storage (a banner is not a book file).
 * No rows are seeded (spec §8.4).
 */
class BannerResource extends BaseAdminResource
{
    protected static ?string $model = Banner::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('admin.banners.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.banners.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.banners.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label(__('admin.banners.title'))
                ->required()
                ->maxLength(255),
            FileUpload::make('image_path')
                ->label(__('admin.banners.image'))
                ->disk('public')
                ->directory('banners')
                ->image()
                ->maxSize(5120),
            TextInput::make('link_url')
                ->label(__('admin.banners.link_url'))
                ->url()
                ->maxLength(500),
            TextInput::make('sort_order')
                ->label(__('admin.banners.sort_order'))
                ->numeric()
                ->default(0),
            Checkbox::make('is_active')
                ->label(__('admin.banners.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.banners.title'))
                    ->searchable(),
                TextColumn::make('link_url')
                    ->label(__('admin.banners.link_url'))
                    ->limit(40),
                TextColumn::make('sort_order')
                    ->label(__('admin.banners.sort_order'))
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label(__('admin.banners.is_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? '✓' : '—'),
                TextColumn::make('created_at')
                    ->label(__('admin.banners.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBanners::route('/'),
            'create' => Pages\CreateBanner::route('/create'),
            'edit' => Pages\EditBanner::route('/{record}/edit'),
        ];
    }
}
