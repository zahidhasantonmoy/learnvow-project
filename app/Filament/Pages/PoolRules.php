<?php

namespace App\Filament\Pages;

use App\Services\AdminService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
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
 * Builder C — superPool (spec §4.2). The pool rules the authors see are
 * rendered from THESE settings — one source of truth (the settings
 * table). Super Admin only; changes are audited.
 *
 * Defaults (spec §7.16, A1): share 80, fee 2%, count 10 min / 10%,
 * min payout ৳500, payout day 10.
 */
class PoolRules extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scale';

    protected static ?string $slug = 'pool-rules';

    protected static ?int $navigationSort = 22;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin.pool.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.super_admin');
    }

    public function getTitle(): string
    {
        return __('admin.pool.heading');
    }

    public static function canAccess(): bool
    {
        return AdminService::isSuperAdmin(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function defaults(): array
    {
        return [
            'pool.share_pct' => '80',
            'pool.fee_rate_pct' => '2',
            'pool.count_min_minutes' => '10',
            'pool.count_min_percent' => '10',
            'pool.min_payout_bdt' => '500',
            'pool.payout_day' => '10',
        ];
    }

    public static function fieldToKey(): array
    {
        return [
            'share_pct' => 'pool.share_pct',
            'fee_rate_pct' => 'pool.fee_rate_pct',
            'count_min_minutes' => 'pool.count_min_minutes',
            'count_min_percent' => 'pool.count_min_percent',
            'min_payout_bdt' => 'pool.min_payout_bdt',
            'payout_day' => 'pool.payout_day',
        ];
    }

    public static function loadSettings(): array
    {
        $values = static::defaults();

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
                    ->content(__('admin.pool.note')),
                TextInput::make('share_pct')
                    ->label(__('admin.pool.share_pct'))
                    ->numeric()->minValue(0)->maxValue(100)->required(),
                TextInput::make('fee_rate_pct')
                    ->label(__('admin.pool.fee_rate_pct'))
                    ->numeric()->minValue(0)->maxValue(100)->required(),
                TextInput::make('count_min_minutes')
                    ->label(__('admin.pool.count_min_minutes'))
                    ->numeric()->minValue(1)->required(),
                TextInput::make('count_min_percent')
                    ->label(__('admin.pool.count_min_percent'))
                    ->numeric()->minValue(1)->maxValue(100)->required(),
                TextInput::make('min_payout_bdt')
                    ->label(__('admin.pool.min_payout_bdt'))
                    ->numeric()->minValue(0)->required(),
                TextInput::make('payout_day')
                    ->label(__('admin.pool.payout_day'))
                    ->numeric()->minValue(1)->maxValue(28)->required(),
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
                            ->label(__('admin.pool.save'))
                            ->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        if (! SchemaFacade::hasTable('settings')) {
            Notification::make()->title(__('admin.pay_config.no_settings_table'))->danger()->send();

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
            'message' => 'Changed pool rules',
            'changed' => $changed,
        ]);

        Notification::make()
            ->title(__('admin.pool.saved'))
            ->success()
            ->send();
    }
}
