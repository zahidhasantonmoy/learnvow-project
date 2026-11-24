<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Illuminate\Validation\ValidationException;

/**
 * Builder C — adminAnnounce coupons (spec §3.4). Admin CAN create coupons
 * (user decision 2026-11-22). The discount / duration must stay inside
 * Super Admin's settings limits (promo.max_percent,
 * promo.max_duration_days) — with no limits set, Admin may create any
 * coupon (assumption A12). Author promos (coupons.author_id) are
 * Builder A's path; they only show here, never edited into another
 * author's coupon.
 */
class CouponResource extends BaseAdminResource
{
    protected static ?string $model = Coupon::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getModelLabel(): string
    {
        return __('admin.coupons.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.coupons.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.coupons.plural');
    }

    /** Super Admin's promo limits, or null when unset (then: no limit, A12). */
    public static function promoLimits(): array
    {
        $limits = ['max_percent' => null, 'max_duration_days' => null];

        if (! SchemaFacade::hasTable('settings')) {
            return $limits;
        }

        $rows = DB::table('settings')
            ->whereIn('key', ['promo.max_percent', 'promo.max_duration_days'])
            ->pluck('value', 'key');

        if (($rows['promo.max_percent'] ?? '') !== '' && ($rows['promo.max_percent'] ?? null) !== null) {
            $limits['max_percent'] = (int) $rows['promo.max_percent'];
        }

        if (($rows['promo.max_duration_days'] ?? '') !== '' && ($rows['promo.max_duration_days'] ?? null) !== null) {
            $limits['max_duration_days'] = (int) $rows['promo.max_duration_days'];
        }

        return $limits;
    }

    /** Throws a validation error when the data breaks a set promo limit. */
    public static function assertWithinPromoLimits(array $data): void
    {
        $limits = static::promoLimits();

        if ($limits['max_percent'] !== null
            && isset($data['percent'])
            && (int) $data['percent'] > $limits['max_percent']
        ) {
            throw ValidationException::withMessages([
                'percent' => __('admin.coupons.limit_note'),
            ]);
        }

        if ($limits['max_duration_days'] !== null
            && ! empty($data['starts_at'])
            && ! empty($data['ends_at'])
        ) {
            $days = (int) ceil(
                (strtotime((string) $data['ends_at']) - strtotime((string) $data['starts_at'])) / 86400
            );

            if ($days > $limits['max_duration_days']) {
                throw ValidationException::withMessages([
                    'ends_at' => __('admin.coupons.limit_note'),
                ]);
            }
        }
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')
                ->label(__('admin.coupons.code'))
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true),
            TextInput::make('percent')
                ->label(__('admin.coupons.percent'))
                ->numeric()
                ->minValue(1)
                ->maxValue(100)
                ->required(),
            Select::make('applies_to')
                ->label(__('admin.coupons.applies_to'))
                ->options(['all' => 'All', 'books' => 'Books', 'subscription' => 'Subscription'])
                ->default('all')
                ->required(),
            DateTimePicker::make('starts_at')
                ->label(__('admin.coupons.starts_at')),
            DateTimePicker::make('ends_at')
                ->label(__('admin.coupons.ends_at')),
            TextInput::make('max_uses')
                ->label(__('admin.coupons.max_uses'))
                ->numeric()
                ->minValue(1),
            TextInput::make('max_uses_per_user')
                ->label(__('admin.coupons.max_uses_per_user'))
                ->numeric()
                ->minValue(1),
            Checkbox::make('is_active')
                ->label(__('admin.coupons.is_active'))
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label(__('admin.coupons.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('percent')
                    ->label(__('admin.coupons.percent'))
                    ->formatStateUsing(fn ($state) => $state.'%')
                    ->sortable(),
                TextColumn::make('applies_to')
                    ->label(__('admin.coupons.applies_to')),
                TextColumn::make('used_count')
                    ->label(__('admin.coupons.used_count'))
                    ->sortable(),
                TextColumn::make('is_active')
                    ->label(__('admin.coupons.is_active'))
                    ->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? '✓' : '—'),
                TextColumn::make('ends_at')
                    ->label(__('admin.coupons.ends_at'))
                    ->dateTime(),
                TextColumn::make('created_at')
                    ->label(__('admin.coupons.created_at'))
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
            'index' => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit' => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}
