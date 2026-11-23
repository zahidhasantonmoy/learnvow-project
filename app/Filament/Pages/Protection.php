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
 * Builder C — superProtection (spec §4.7). SETTINGS ONLY — no enforcement
 * in Phase 3 (watermark enforcement for print-ready PDFs and encrypted
 * downloads land in Phase 4; the device limit is enforced in Phase 4).
 * Super Admin only; changes are audited.
 */
class Protection extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $slug = 'protection';

    protected static ?int $navigationSort = 24;

    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return __('admin.protection.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.super_admin');
    }

    public function getTitle(): string
    {
        return __('admin.protection.heading');
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
            'protection.watermark_enabled' => '1',
            'protection.device_limit' => '3',
            'protection.encrypted_downloads' => '0',
        ];
    }

    public static function fieldToKey(): array
    {
        return [
            'watermark_enabled' => 'protection.watermark_enabled',
            'device_limit' => 'protection.device_limit',
            'encrypted_downloads' => 'protection.encrypted_downloads',
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
                    ->content(__('admin.protection.note')),
                TextInput::make('watermark_enabled')
                    ->label(__('admin.protection.watermark_enabled'))
                    ->numeric()->minValue(0)->maxValue(1)->required(),
                TextInput::make('device_limit')
                    ->label(__('admin.protection.device_limit'))
                    ->numeric()->minValue(1)->required(),
                TextInput::make('encrypted_downloads')
                    ->label(__('admin.protection.encrypted_downloads'))
                    ->numeric()->minValue(0)->maxValue(1)->required(),
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
                            ->label(__('admin.protection.save'))
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
            'message' => 'Changed protection settings',
            'changed' => $changed,
        ]);

        Notification::make()
            ->title(__('admin.protection.saved'))
            ->success()
            ->send();
    }
}
