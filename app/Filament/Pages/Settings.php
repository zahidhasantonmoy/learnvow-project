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
use Illuminate\Support\Facades\Storage;

/**
 * Builder C — Super Admin platform settings (spec §4.1 superSettings).
 *
 * Phase 3 FULL version: DB-backed (settings table), all §7.16 keys —
 * pool.* (same keys PoolRules edits), promo.* (blank until Super Admin
 * sets them, A12), protection.* (same keys the Protection page edits),
 * upload.* — plus the site fields. Super Admin only; every save is
 * audited (settings.changed with the changed keys).
 *
 * The Phase 1 shell stored JSON in learnvow-settings.json; those values
 * are read once as legacy defaults for the site fields only.
 * Price/payment keys live on PayConfig, not here.
 */
class Settings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $slug = 'settings';

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.super_admin');
    }

    public function getTitle(): string
    {
        return __('admin.settings.heading');
    }

    public static function canAccess(): bool
    {
        return AdminService::isSuperAdmin(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    /** All settings keys with defaults (spec §7.16). */
    public static function defaults(): array
    {
        return [
            'site.name' => 'LearnVow',
            'site.default_locale' => 'bn',
            'site.support_email' => '',
            'site.maintenance_message' => '',
            'pool.share_pct' => '80',
            'pool.fee_rate_pct' => '2',
            'pool.count_min_minutes' => '10',
            'pool.count_min_percent' => '10',
            'pool.min_payout_bdt' => '500',
            'pool.payout_day' => '10',
            'promo.max_percent' => '',
            'promo.max_duration_days' => '',
            'protection.watermark_enabled' => '1',
            'protection.device_limit' => '3',
            'protection.encrypted_downloads' => '0',
            'upload.max_epub_mb' => '50',
            'upload.max_audio_mb' => '500',
            'upload.max_cover_mb' => '5',
            'upload.min_cover_width' => '1600',
            'upload.min_cover_height' => '2400',
        ];
    }

    /** Form field name => settings key (flat names — no dots in Filament state). */
    public static function fieldToKey(): array
    {
        return [
            'site_name' => 'site.name',
            'default_locale' => 'site.default_locale',
            'support_email' => 'site.support_email',
            'maintenance_message' => 'site.maintenance_message',
            'pool_share_pct' => 'pool.share_pct',
            'pool_fee_rate_pct' => 'pool.fee_rate_pct',
            'pool_count_min_minutes' => 'pool.count_min_minutes',
            'pool_count_min_percent' => 'pool.count_min_percent',
            'pool_min_payout_bdt' => 'pool.min_payout_bdt',
            'pool_payout_day' => 'pool.payout_day',
            'promo_max_percent' => 'promo.max_percent',
            'promo_max_duration_days' => 'promo.max_duration_days',
            'protection_watermark_enabled' => 'protection.watermark_enabled',
            'protection_device_limit' => 'protection.device_limit',
            'protection_encrypted_downloads' => 'protection.encrypted_downloads',
            'upload_max_epub_mb' => 'upload.max_epub_mb',
            'upload_max_audio_mb' => 'upload.max_audio_mb',
            'upload_max_cover_mb' => 'upload.max_cover_mb',
            'upload_min_cover_width' => 'upload.min_cover_width',
            'upload_min_cover_height' => 'upload.min_cover_height',
        ];
    }

    /** Legacy Phase 1 JSON values, site fields only. */
    protected static function legacySiteValues(): array
    {
        if (! Storage::disk('local')->exists('learnvow-settings.json')) {
            return [];
        }

        $stored = json_decode(Storage::disk('local')->get('learnvow-settings.json'), true);

        if (! is_array($stored)) {
            return [];
        }

        return array_filter([
            'site.name' => $stored['site_name'] ?? null,
            'site.default_locale' => $stored['default_locale'] ?? null,
            'site.support_email' => $stored['support_email'] ?? null,
            'site.maintenance_message' => $stored['maintenance_message'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');
    }

    /** Current settings: stored DB values over defaults (legacy JSON as fallback). */
    public static function loadSettings(): array
    {
        $values = array_merge(static::defaults(), static::legacySiteValues());

        if (! SchemaFacade::hasTable('settings')) {
            return $values;
        }

        foreach (DB::table('settings')->pluck('value', 'key') as $key => $value) {
            if (array_key_exists($key, $values)) {
                $values[$key] = $value ?? '';
            }
        }

        return $values;
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
                Placeholder::make('note')
                    ->content(__('admin.settings.note')),
                TextInput::make('site_name')
                    ->label(__('admin.settings.site_name'))
                    ->required()
                    ->maxLength(100),
                Select::make('default_locale')
                    ->label(__('admin.settings.default_locale'))
                    ->options(['bn' => 'বাংলা', 'en' => 'English'])
                    ->required(),
                TextInput::make('support_email')
                    ->label(__('admin.settings.support_email'))
                    ->email()
                    ->maxLength(255),
                Textarea::make('maintenance_message')
                    ->label(__('admin.settings.maintenance_message'))
                    ->rows(2),
                Placeholder::make('pool_heading')
                    ->content(__('admin.settings_extra.pool_heading')),
                TextInput::make('pool_share_pct')
                    ->label(__('admin.pool.share_pct'))
                    ->numeric()->minValue(0)->maxValue(100)->required(),
                TextInput::make('pool_fee_rate_pct')
                    ->label(__('admin.pool.fee_rate_pct'))
                    ->numeric()->minValue(0)->maxValue(100)->required(),
                TextInput::make('pool_count_min_minutes')
                    ->label(__('admin.pool.count_min_minutes'))
                    ->numeric()->minValue(1)->required(),
                TextInput::make('pool_count_min_percent')
                    ->label(__('admin.pool.count_min_percent'))
                    ->numeric()->minValue(1)->maxValue(100)->required(),
                TextInput::make('pool_min_payout_bdt')
                    ->label(__('admin.pool.min_payout_bdt'))
                    ->numeric()->minValue(0)->required(),
                TextInput::make('pool_payout_day')
                    ->label(__('admin.pool.payout_day'))
                    ->numeric()->minValue(1)->maxValue(28)->required(),
                Placeholder::make('promo_heading')
                    ->content(__('admin.settings_extra.promo_heading')),
                TextInput::make('promo_max_percent')
                    ->label(__('admin.settings_extra.promo_max_percent'))
                    ->numeric()->minValue(1)->maxValue(100),
                TextInput::make('promo_max_duration_days')
                    ->label(__('admin.settings_extra.promo_max_duration_days'))
                    ->numeric()->minValue(1),
                Placeholder::make('protection_heading')
                    ->content(__('admin.settings_extra.protection_heading')),
                TextInput::make('protection_watermark_enabled')
                    ->label(__('admin.protection.watermark_enabled'))
                    ->numeric()->minValue(0)->maxValue(1)->required(),
                TextInput::make('protection_device_limit')
                    ->label(__('admin.protection.device_limit'))
                    ->numeric()->minValue(1)->required(),
                TextInput::make('protection_encrypted_downloads')
                    ->label(__('admin.protection.encrypted_downloads'))
                    ->numeric()->minValue(0)->maxValue(1)->required(),
                Placeholder::make('upload_heading')
                    ->content(__('admin.settings_extra.upload_heading')),
                TextInput::make('upload_max_epub_mb')
                    ->label(__('admin.settings_extra.upload_max_epub_mb'))
                    ->numeric()->minValue(1)->required(),
                TextInput::make('upload_max_audio_mb')
                    ->label(__('admin.settings_extra.upload_max_audio_mb'))
                    ->numeric()->minValue(1)->required(),
                TextInput::make('upload_max_cover_mb')
                    ->label(__('admin.settings_extra.upload_max_cover_mb'))
                    ->numeric()->minValue(1)->required(),
                TextInput::make('upload_min_cover_width')
                    ->label(__('admin.settings_extra.upload_min_cover_width'))
                    ->numeric()->minValue(1)->required(),
                TextInput::make('upload_min_cover_height')
                    ->label(__('admin.settings_extra.upload_min_cover_height'))
                    ->numeric()->minValue(1)->required(),
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
                            ->label(__('admin.settings.save'))
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        if (! SchemaFacade::hasTable('settings')) {
            Notification::make()
                ->title(__('admin.pay_config.no_settings_table'))
                ->danger()
                ->send();

            return;
        }

        $data = $this->form->getState();
        $before = static::loadSettings();
        $changed = [];

        foreach (static::fieldToKey() as $field => $key) {
            $value = (string) ($data[$field] ?? static::defaults()[$key]);

            if (($before[$key] ?? null) !== $value) {
                $changed[$key] = ['from' => $before[$key] ?? null, 'to' => $value];
            }

            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }

        AdminService::writeAudit(auth()->user(), 'settings.changed', 'settings', null, [
            'message' => 'Changed platform settings',
            'changed' => $changed,
        ]);

        Notification::make()
            ->title(__('admin.settings_extra.saved'))
            ->success()
            ->send();
    }
}
