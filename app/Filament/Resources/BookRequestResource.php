<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookRequestResource\Pages;
use App\Models\BookRequest;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Admin queue for book requests (§4.9): review open → approved/rejected. */
class BookRequestResource extends BaseAdminResource
{
    protected static ?string $model = BookRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?int $navigationSort = 20;

    protected static ?string $recordTitleAttribute = 'title';

    public static function getModelLabel(): string
    {
        return __('admin.book_requests.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.book_requests.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.book_requests.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')
                ->label(__('admin.book_requests.status'))
                ->options([
                    'open' => 'Open',
                    'reviewing' => 'Reviewing',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ])
                ->required(),
            Textarea::make('review_note')
                ->label(__('admin.book_requests.review_note'))
                ->maxLength(1000),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('admin.book_requests.title'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author_name')
                    ->label(__('admin.book_requests.author_name'))
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label(__('admin.book_requests.requested_by')),
                TextColumn::make('status')
                    ->label(__('admin.book_requests.status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.book_requests.created_at'))
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
            'index' => Pages\ListBookRequests::route('/'),
            'edit' => Pages\EditBookRequest::route('/{record}/edit'),
        ];
    }
}
