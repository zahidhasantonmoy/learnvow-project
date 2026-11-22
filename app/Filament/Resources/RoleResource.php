<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RoleResource\Pages;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

/**
 * Builder C — superRoles (spec §4.5). SUPER ADMIN ONLY — enforced by
 * BaseAdminResource::$superAdminOnly (navigation, view, create, edit,
 * delete all deny for Admin). The `admin` / `super-admin` roles can
 * neither be renamed nor deleted here.
 */
class RoleResource extends BaseAdminResource
{
    protected static ?string $model = Role::class;

    protected static bool $superAdminOnly = true;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.roles.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.roles.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.roles.plural');
    }

    private static function isProtected(Role $record): bool
    {
        return in_array($record->name, ['admin', 'super-admin'], true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label(__('admin.roles.name'))
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true)
                ->disabled(fn (?Role $record) => $record !== null && static::isProtected($record)),
            TextInput::make('guard_name')
                ->label(__('admin.roles.guard'))
                ->default('web')
                ->required()
                ->maxLength(255)
                ->disabled(fn (?Role $record) => $record !== null),
            CheckboxList::make('permissions')
                ->label(__('admin.roles.permissions'))
                ->relationship('permissions', 'name')
                ->columns(2)
                ->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('admin.roles.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('guard_name')
                    ->label(__('admin.roles.guard')),
                TextColumn::make('permissions_count')
                    ->label(__('admin.roles.permissions'))
                    ->counts('permissions'),
                TextColumn::make('users_count')
                    ->label(__('admin.roles.users_count'))
                    ->counts('users'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (Role $record) => ! static::isProtected($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageRoles::route('/'),
        ];
    }
}
