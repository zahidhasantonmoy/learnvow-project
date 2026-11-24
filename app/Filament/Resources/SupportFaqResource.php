<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupportFaqResource\Pages;
use App\Models\Faq;
use App\Services\AdminService;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Builder C — supportFaq (spec §3.5). FAQ entries (bn + en), managed by
 * Support / Admin / Super Admin. No rows are seeded (spec §8.4).
 */
class SupportFaqResource extends BaseAdminResource
{
    protected static ?string $model = Faq::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'question';

    protected static function resourceAllowed(): bool
    {
        $user = static::currentUser();

        return $user !== null && AdminService::userHasRole($user, ['support', 'admin', 'super-admin']);
    }

    public static function getModelLabel(): string
    {
        return __('support.faqs.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('support.faqs.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('support.faqs.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('question')
                ->label(__('support.faqs.question'))
                ->required()
                ->rows(2),
            Textarea::make('question_en')
                ->label(__('support.faqs.question_en'))
                ->rows(2),
            Textarea::make('answer')
                ->label(__('support.faqs.answer'))
                ->required()
                ->rows(4),
            Textarea::make('answer_en')
                ->label(__('support.faqs.answer_en'))
                ->rows(4),
            TextInput::make('category')
                ->label(__('support.faqs.category'))
                ->maxLength(100),
            TextInput::make('sort_order')
                ->label(__('support.faqs.sort_order'))
                ->numeric()
                ->default(0),
            Checkbox::make('is_active')
                ->label(__('support.faqs.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('question')
                    ->label(__('support.faqs.question'))
                    ->searchable()
                    ->limit(60),
                TextColumn::make('category')
                    ->label(__('support.faqs.category'))
                    ->searchable(),
                TextColumn::make('sort_order')
                    ->label(__('support.faqs.sort_order'))
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label(__('support.faqs.is_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? '✓' : '—'),
                TextColumn::make('created_at')
                    ->label(__('support.faqs.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqs::route('/'),
            'create' => Pages\CreateFaq::route('/create'),
            'edit' => Pages\EditFaq::route('/{record}/edit'),
        ];
    }
}
