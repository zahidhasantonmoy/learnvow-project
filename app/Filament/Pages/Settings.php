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
use Illuminate\Support\Facades\Storage;

/**
 * Builder C — Super Admin platform settings (spec §4.5 superSettings).
 * Phase 1 shell: basic settings persisted as JSON (no settings table
 * exists in Phase 1 — spec §1.10; full DB-backed settings are Phase 3).
 * Super Admin only — Admin is blocked by canAccess() and by the panel
 * middleware, and the page is hidden from navigation for Admin.
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

    public function mount(): void
    {
        $this->form->fill($this->loadSettings());
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
                TextInput::make('subscription_price_bdt')
                    ->label(__('admin.settings.subscription_price'))
                    ->numeric()
                    ->minValue(0)
                    ->required(),
                TextInput::make('author_revenue_share_pct')
                    ->label(__('admin.settings.author_share'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100)
                    ->required(),
                TextInput::make('support_email')
                    ->label(__('admin.settings.support_email'))
                    ->email()
                    ->maxLength(255),
                Textarea::make('maintenance_message')
                    ->label(__('admin.settings.maintenance_message'))
                    ->rows(2),
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
        $data = $this->form->getState();

        Storage::disk('local')->put(
            'learnvow-settings.json',
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        Notification::make()
            ->title(__('admin.settings.saved'))
            ->success()
            ->send();
    }

    /** Current settings: stored JSON over the spec's frozen defaults. */
    public static function loadSettings(): array
    {
        $defaults = [
            'site_name' => 'LearnVow',
            'default_locale' => 'bn',
            'subscription_price_bdt' => 99,
            'author_revenue_share_pct' => 80,
            'support_email' => null,
            'maintenance_message' => null,
        ];

        if (! Storage::disk('local')->exists('learnvow-settings.json')) {
            return $defaults;
        }

        $stored = json_decode(Storage::disk('local')->get('learnvow-settings.json'), true);

        return array_merge($defaults, is_array($stored) ? $stored : []);
    }
}
