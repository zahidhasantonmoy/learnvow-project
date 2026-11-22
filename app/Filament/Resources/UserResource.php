<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\AdminService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema as SchemaFacade;

/**
 * Builder C — adminUsers (spec §4.4). List + role changes.
 * Role changes: Admin may never grant/remove `admin` / `super-admin`
 * (roles doc); the single Super Admin account can never lose its role.
 * No user deletion in Phase 1 (the Super Admin account must never be
 * deletable). Phone / status / locale fields appear once Builder A adds
 * those columns to the users table (spec §1.1) — until then the resource
 * renders with the columns that exist.
 */
class UserResource extends BaseAdminResource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('admin.users.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.users.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.users.plural');
    }

    private static function hasColumn(string $column): bool
    {
        return SchemaFacade::hasColumn('users', $column);
    }

    public static function form(Schema $schema): Schema
    {
        $components = [
            TextInput::make('name')
                ->label(__('admin.users.name'))
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label(__('admin.users.email'))
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
        ];

        if (static::hasColumn('phone')) {
            $components[] = TextInput::make('phone')
                ->label(__('admin.users.phone'))
                ->required()
                ->maxLength(20)
                ->unique(ignoreRecord: true);
        }

        if (static::hasColumn('status')) {
            $components[] = Select::make('status')
                ->label(__('admin.users.status'))
                ->options(collect(['active', 'trial', 'expired', 'suspended', 'banned', 'pending-approval'])
                    ->mapWithKeys(fn (string $s) => [$s => __('admin.status.'.str_replace('-', '_', $s))])
                    ->all());
        }

        if (static::hasColumn('locale')) {
            $components[] = Select::make('locale')
                ->label(__('admin.users.locale'))
                ->options(['bn' => 'বাংলা', 'en' => 'English']);
        }

        if (SchemaFacade::hasTable('roles')) {
            $components[] = CheckboxList::make('roles')
                ->label(__('admin.users.roles'))
                ->options(fn () => array_combine(
                    $assignable = AdminService::assignableRolesFor(auth()->user()),
                    $assignable,
                ))
                ->columns(2);
        }

        return $schema->components($components);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('name')->label(__('admin.users.name')),
            TextEntry::make('email')->label(__('admin.users.email')),
            TextEntry::make('roles')
                ->label(__('admin.users.roles'))
                ->state(fn (User $record) => implode(', ', AdminService::userRoles($record)) ?: '—'),
            TextEntry::make('created_at')
                ->label(__('admin.users.created_at'))
                ->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $columns = [
            TextColumn::make('name')
                ->label(__('admin.users.name'))
                ->searchable()
                ->sortable(),
            TextColumn::make('email')
                ->label(__('admin.users.email'))
                ->searchable()
                ->sortable(),
        ];

        if (static::hasColumn('phone')) {
            $columns[] = TextColumn::make('phone')
                ->label(__('admin.users.phone'))
                ->searchable();
        }

        $columns[] = TextColumn::make('roles')
            ->label(__('admin.users.roles'))
            ->state(fn (User $record) => implode(', ', AdminService::userRoles($record)) ?: '—')
            ->badge();

        if (static::hasColumn('status')) {
            $columns[] = TextColumn::make('status')
                ->label(__('admin.users.status'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('admin.status.'.str_replace('-', '_', $state)));
        }

        $columns[] = TextColumn::make('created_at')
            ->label(__('admin.users.created_at'))
            ->dateTime()
            ->sortable();

        return $table
            ->columns($columns)
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
