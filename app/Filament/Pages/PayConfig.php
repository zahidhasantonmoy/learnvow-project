<?php

namespace App\Filament\Pages;

use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;

/**
 * Builder C — superPayConfig (spec §3.6, §6.21).
 *
 * SUPER ADMIN ONLY — Admin gets 403 (canAccess + navigation hidden).
 * Reads/writes the `settings` table (Builder B's migration, spec §6.21),
 * key/value. Keys (spec §6.21): payment.bkash_number, payment.nagad_number,
 * payment.fee_note, subscription.price_bdt (99), referral.reward_type,
 * referral.reward_value, points.per_purchase, points.per_finish,
 * payment.sla_hours (12).
 *
 * The prototype's bKash/Nagad numbers are DEMO numbers (A16) — they are
 * never seeded or hardcoded here; until the Super Admin sets real numbers
 * the fields stay empty and checkout warns the user.
 */
class PayConfig extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $slug = 'pay-config';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin.pay_config.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.super_admin');
    }

    public function getTitle(): string
    {
        return __('admin.pay_config.heading');
    }

    public static function canAccess(): bool
    {
        return AdminService::isSuperAdmin(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** All PayConfig keys with their defaults (spec §6.21). */
    public static function defaults(): array
    {
        return [
            'payment.bkash_number' => '',
            'payment.nagad_number' => '',
            'payment.fee_note' => '',
            'subscription.price_bdt' => '99',
            'referral.reward_type' => 'premium_week',
            'referral.reward_value' => '7',
            'points.per_purchase' => '',
            'points.per_finish' => '',
            'payment.sla_hours' => '12',
            // Phase 4 (§3.1, §11.1): international plan + gateway seam.
            'intl.price_bdt' => '199',
            'intl.plan_slug' => 'premium-monthly-intl',
            'intl.trial_days' => '7',
            'payments.gateway' => 'manual',
            'sslcommerz.store_id' => '',
            'sslcommerz.store_password' => '',
        ];
    }

    public static function settingsTableExists(): bool
    {
        return SchemaFacade::hasTable('settings');
    }

    /** Current values: stored settings over the defaults. */
    public static function loadSettings(): array
    {
        $values = static::defaults();

        if (! static::settingsTableExists()) {
            return $values;
        }

        foreach (DB::table('settings')->pluck('value', 'key') as $key => $value) {
            if (array_key_exists($key, $values)) {
                $values[$key] = $value ?? '';
            }
        }

        return $values;
    }

    /** Form field name => settings key. Field names stay flat (no dots) —
     *  Filament treats dotted names as nested state, which would not map
     *  back to the flat `settings` keys. */
    public static function fieldToKey(): array
    {
        return [
            'bkash_number' => 'payment.bkash_number',
            'nagad_number' => 'payment.nagad_number',
            'fee_note' => 'payment.fee_note',
            'subscription_price_bdt' => 'subscription.price_bdt',
            'referral_reward_type' => 'referral.reward_type',
            'referral_reward_value' => 'referral.reward_value',
            'points_per_purchase' => 'points.per_purchase',
            'points_per_finish' => 'points.per_finish',
            'sla_hours' => 'payment.sla_hours',
            'intl_price_bdt' => 'intl.price_bdt',
            'intl_plan_slug' => 'intl.plan_slug',
            'intl_trial_days' => 'intl.trial_days',
            'payments_gateway' => 'payments.gateway',
            'sslcommerz_store_id' => 'sslcommerz.store_id',
            'sslcommerz_store_password' => 'sslcommerz.store_password',
        ];
    }

    public function mount(): void
    {
        $settings = static::loadSettings();
        $flat = [];

        foreach (static::fieldToKey() as $field => $key) {
            $flat[$field] = $settings[$key] ?? static::defaults()[$key];
        }

        $this->form->fill($flat);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('bkash_number')
                    ->label(__('admin.pay_config.bkash_number'))
                    ->maxLength(20)
                    ->placeholder(__('admin.pay_config.not_set')),
                TextInput::make('nagad_number')
                    ->label(__('admin.pay_config.nagad_number'))
                    ->maxLength(20)
                    ->placeholder(__('admin.pay_config.not_set')),
                Textarea::make('fee_note')
                    ->label(__('admin.pay_config.fee_note'))
                    ->rows(2)
                    ->maxLength(500),
                TextInput::make('subscription_price_bdt')
                    ->label(__('admin.pay_config.subscription_price'))
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                Select::make('referral_reward_type')
                    ->label(__('admin.pay_config.referral_reward_type'))
                    ->options([
                        'premium_week' => __('admin.pay_config.reward_premium_week'),
                        'wallet_credit' => __('admin.pay_config.reward_wallet_credit'),
                    ])
                    ->required(),
                TextInput::make('referral_reward_value')
                    ->label(__('admin.pay_config.referral_reward_value'))
                    ->maxLength(50),
                TextInput::make('points_per_purchase')
                    ->label(__('admin.pay_config.points_per_purchase'))
                    ->numeric()
                    ->minValue(0),
                TextInput::make('points_per_finish')
                    ->label(__('admin.pay_config.points_per_finish'))
                    ->numeric()
                    ->minValue(0),
                TextInput::make('sla_hours')
                    ->label(__('admin.pay_config.sla_hours'))
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(168)
                    ->required(),
                Placeholder::make('intl_heading')
                    ->content(__('admin.pay_config.intl_heading')),
                TextInput::make('intl_price_bdt')
                    ->label(__('admin.pay_config.intl_price_bdt'))
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('intl_plan_slug')
                    ->label(__('admin.pay_config.intl_plan_slug'))
                    ->maxLength(100)
                    ->required(),
                TextInput::make('intl_trial_days')
                    ->label(__('admin.pay_config.intl_trial_days'))
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('payments_gateway')
                    ->label(__('admin.pay_config.payments_gateway'))
                    ->maxLength(50)
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('manual'),
                TextInput::make('sslcommerz_store_id')
                    ->label(__('admin.pay_config.sslcommerz_store_id'))
                    ->maxLength(100),
                TextInput::make('sslcommerz_store_password')
                    ->label(__('admin.pay_config.sslcommerz_store_password'))
                    ->password()
                    ->maxLength(100),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label(__('admin.pay_config.save'))
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        if (! static::settingsTableExists()) {
            Notification::make()
                ->title(__('admin.pay_config.no_settings_table'))
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();

        foreach (static::fieldToKey() as $field => $key) {
            $value = $data[$field] ?? static::defaults()[$key];

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => (string) $value, 'updated_at' => now()]
            );
        }

        Notification::make()
            ->title(__('admin.pay_config.saved'))
            ->success()
            ->send();
    }
}
