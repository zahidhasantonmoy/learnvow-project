<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AnnouncementResource\Pages;
use App\Models\Announcement;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Builder C — adminAnnounce (spec §3.4). Announcements to all / readers /
 * authors / premium. No rows are seeded (spec §8.4).
 */
class AnnouncementResource extends BaseAdminResource
{
    protected static ?string $model = Announcement::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('admin.announcements.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.announcements.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.announcements.plural');
    }

    public static function audienceOptions(): array
    {
        return collect(['all', 'readers', 'authors', 'premium'])
            ->mapWithKeys(fn (string $s) => [$s => __('admin.announcements.audience_'.$s)])
            ->all();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label(__('admin.announcements.title'))
                ->required()
                ->maxLength(255),
            TextInput::make('title_en')
                ->label(__('admin.announcements.title_en'))
                ->maxLength(255),
            Textarea::make('body')
                ->label(__('admin.announcements.body'))
                ->required()
                ->rows(4),
            Textarea::make('body_en')
                ->label(__('admin.announcements.body_en'))
                ->rows(4),
            Select::make('audience')
                ->label(__('admin.announcements.audience'))
                ->options(static::audienceOptions())
                ->default('all')
                ->required(),
            DateTimePicker::make('starts_at')
                ->label(__('admin.announcements.starts_at')),
            DateTimePicker::make('ends_at')
                ->label(__('admin.announcements.ends_at')),
            Checkbox::make('is_active')
                ->label(__('admin.announcements.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.announcements.title'))
                    ->searchable()
                    ->limit(50),
                TextColumn::make('audience')
                    ->label(__('admin.announcements.audience'))
                    ->formatStateUsing(fn (string $state) => __('admin.announcements.audience_'.$state)),
                TextColumn::make('is_active')
                    ->label(__('admin.announcements.is_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? '✓' : '—'),
                TextColumn::make('starts_at')
                    ->label(__('admin.announcements.starts_at'))
                    ->dateTime(),
                TextColumn::make('created_at')
                    ->label(__('admin.announcements.created_at'))
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
            'index' => Pages\ListAnnouncements::route('/'),
            'create' => Pages\CreateAnnouncement::route('/create'),
            'edit' => Pages\EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
