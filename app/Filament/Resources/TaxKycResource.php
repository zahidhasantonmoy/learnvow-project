<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TaxKycResource\Pages;
use App\Models\TaxKycProfile;
use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Tax / KYC verification (Phase 4 spec §3.3, §14.7). SUPER ADMIN ONLY.
 * NID / TIN / bank numbers are encrypted and NEVER shown in full —
 * only the last 3 digits, via the model's masked*() helpers.
 */
class TaxKycResource extends BaseAdminResource
{
    protected static ?string $model = TaxKycProfile::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 13;

    protected static ?string $recordTitleAttribute = 'legal_name';

    protected static bool $superAdminOnly = true;

    public static function getModelLabel(): string
    {
        return __('admin.tax_kyc.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.tax_kyc.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.tax_kyc.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.super_admin');
    }

    public static function statusOptions(): array
    {
        return collect(TaxKycProfile::STATUSES)
            ->mapWithKeys(fn (string $s) => [$s => __('admin.tax_kyc.status_'.$s)])
            ->all();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('author.name')->label(__('admin.tax_kyc.author')),
            TextEntry::make('legal_name')->label(__('admin.tax_kyc.legal_name')),
            TextEntry::make('nid_no')->label(__('admin.tax_kyc.nid'))
                ->state(fn (TaxKycProfile $record) => $record->maskedNid()),
            TextEntry::make('tin_no')->label(__('admin.tax_kyc.tin'))
                ->state(fn (TaxKycProfile $record) => $record->maskedTin())
                ->placeholder('—'),
            TextEntry::make('bank_account_number')->label(__('admin.tax_kyc.bank'))
                ->state(fn (TaxKycProfile $record) => $record->maskedBankAccount())
                ->placeholder('—'),
            TextEntry::make('address')->label(__('author.tax_kyc_address'))->placeholder('—'),
            TextEntry::make('status')->label(__('admin.tax_kyc.status'))
                ->badge()
                ->formatStateUsing(fn (string $state) => __('admin.tax_kyc.status_'.$state)),
            TextEntry::make('review_note')->label(__('admin.tax_kyc.review_note'))->placeholder('—'),
            TextEntry::make('created_at')->label(__('admin.tax_kyc.created_at'))->dateTime(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('author.name')->label(__('admin.tax_kyc.author')),
                TextColumn::make('legal_name')->label(__('admin.tax_kyc.legal_name')),
                TextColumn::make('status')->label(__('admin.tax_kyc.status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state) => __('admin.tax_kyc.status_'.$state)),
                TextColumn::make('created_at')->label(__('admin.tax_kyc.created_at'))->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(static::statusOptions()),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTaxKycProfiles::route('/'),
            'view' => Pages\ViewTaxKycProfile::route('/{record}'),
        ];
    }
}
