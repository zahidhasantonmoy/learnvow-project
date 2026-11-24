<?php

namespace App\Filament\Pages;

use App\Services\AdminService;
use App\Services\AiService;
use Filament\Forms\Components\Placeholder;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * AI features status (Phase 4 spec §7.2). SUPER ADMIN ONLY.
 * Seam only — shows `disabled`, the (empty) provider, and the seam
 * methods. There is deliberately NO API-key field here: keys belong in
 * .env / the secure vault once a provider is chosen (spec A13).
 */
class AiFeatures extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $slug = 'ai-features';

    protected static ?int $navigationSort = 26;

    public static function getNavigationLabel(): string
    {
        return __('admin.ai_features.label');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.super_admin');
    }

    public function getTitle(): string
    {
        return __('admin.ai_features.heading');
    }

    public static function canAccess(): bool
    {
        return AdminService::isSuperAdmin(auth()->user());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function content(Schema $schema): Schema
    {
        $ai = app(AiService::class);

        return $schema->components([
            Placeholder::make('intro')
                ->content(__('admin.ai_features.intro')),
            Placeholder::make('status')
                ->label(__('admin.ai_features.heading'))
                ->content($ai->enabled()
                    ? __('admin.ai_features.status_enabled')
                    : __('admin.ai_features.status_disabled')),
            Placeholder::make('provider')
                ->label(__('admin.ai_features.provider'))
                ->content($ai->provider() ?? __('admin.ai_features.provider_empty')),
            Placeholder::make('methods')
                ->label(__('admin.ai_features.methods_heading'))
                ->content(
                    __('admin.ai_features.method_summarize')."\n"
                    .__('admin.ai_features.method_recommend')."\n"
                    .__('admin.ai_features.method_narration')
                ),
        ]);
    }
}
