<?php

namespace App\Filament\Auth;

use App\Models\User;
use App\Services\AdminService;
use Filament\Auth\Pages\Login;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * Builder C — /admin login with phone + password (spec §1.1: phone is the
 * login ID; one account per phone). Filament's default login wants an
 * email, but the seeded demo accounts have no email at all, so the
 * default page could never sign an admin in. Rate limiting and the
 * panel-role check (AuthenticateAdminPanel) still apply.
 */
class AdminLogin extends Login
{
    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getPhoneFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getRememberFormComponent(),
        ]);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('admin.users.phone'))
            ->tel()
            ->required()
            ->autocomplete()
            ->autofocus();
    }

    /**
     * Filament's default allows any user when the model does not implement
     * FilamentUser — so a Reader with valid credentials would "log in"
     * and only hit the 403 afterwards. Reject non-admins here, at login.
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        return $user instanceof User && AdminService::canAccessAdminPanel($user);
    }

    protected function getCredentialsFromFormData(#[\SensitiveParameter] array $data): array
    {
        return [
            'phone' => $data['phone'],
            'password' => $data['password'],
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.phone' => __('filament-panels::auth/pages/login.messages.failed'),
        ]);
    }
}
