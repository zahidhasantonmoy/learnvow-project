<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BadgeResource\Pages;
use App\Models\Badge;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Badge definitions (§4.5). Seeded badges can be edited; new ones added. */
class BadgeResource extends BaseAdminResource
{
    protected static ?string $model = Badge::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 21;

    protected static ?string $recordTitleAttribute = 'slug';

    public static function getModelLabel(): string
    {
        return __('admin.badges.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.badges.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.badges.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('slug')
                ->label(__('admin.badges.slug'))
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true),
            TextInput::make('name')
                ->label(__('admin.badges.name'))
                ->required()
                ->maxLength(100),
            TextInput::make('name_en')
                ->label(__('admin.badges.name_en'))
                ->maxLength(100),
            Textarea::make('description')
                ->label(__('admin.badges.description'))
                ->required(),
            Textarea::make('description_en')
                ->label(__('admin.badges.description_en')),
            TextInput::make('icon')
                ->label(__('admin.badges.icon'))
                ->required()
                ->maxLength(50),
            TextInput::make('rule_key')
                ->label(__('admin.badges.rule_key'))
                ->required()
                ->maxLength(50),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'asc')
            ->columns([
                TextColumn::make('slug')
                    ->label(__('admin.badges.slug'))
                    ->searchable(),
                TextColumn::make('name')
                    ->label(__('admin.badges.name'))
                    ->searchable(),
                TextColumn::make('rule_key')
                    ->label(__('admin.badges.rule_key')),
                TextColumn::make('user_badges_count')
                    ->label(__('admin.badges.awarded_count'))
                    ->counts('userBadges')
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBadges::route('/'),
            'create' => Pages\CreateBadge::route('/create'),
            'edit' => Pages\EditBadge::route('/{record}/edit'),
        ];
    }
}
