<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SerialResource\Pages;
use App\Models\Serial;
use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Serial management (§4.7): Admin approves via AdminService::approveSerial(). */
class SerialResource extends BaseAdminResource
{
    protected static ?string $model = Serial::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?int $navigationSort = 22;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('admin.serials.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.serials.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.serials.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.serials.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author.name')
                    ->label(__('admin.serials.author')),
                TextColumn::make('status')
                    ->label(__('admin.serials.status'))
                    ->badge(),
                TextColumn::make('approved_at')
                    ->label(__('admin.serials.approved_at'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('episodes_count')
                    ->label(__('admin.serials.episodes_count'))
                    ->counts('episodes'),
            ])
            ->actions([
                Action::make('approve')
                    ->label(__('admin.serials.approve'))
                    ->visible(fn (Serial $record) => $record->approved_at === null)
                    ->action(fn (Serial $record) => app(AdminService::class)->approveSerial($record, auth()->user())),
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSerials::route('/'),
            'edit' => Pages\EditSerial::route('/{record}/edit'),
        ];
    }
}
