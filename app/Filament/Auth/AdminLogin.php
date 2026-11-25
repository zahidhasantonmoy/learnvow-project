<?php

namespace App\Filament\Auth;

use App\Exceptions\DeviceLimitReachedException;
use App\Models\User;
use App\Services\AdminService;
use App\Services\DeviceService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
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

    /**
     * Medium-1 (b): the parent login only does Auth::attempt — it never
     * registers a device, so the 3-device limit was silently bypassed
     * for /admin. Rate limiting and the panel-role check still run in
     * the parent; after a successful parent login, register this web
     * device the same way AuthService::login does. On the limit, drop
     * the session again and show the same distinct device message —
     * the OTP device-removal flow gives a locked-out admin a way back,
     * so enforcing the limit here is safe. A null response (rate
     * limited, or an MFA challenge pending) means nobody is logged in
     * yet, so there is no device to register.
     */
    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response === null) {
            return null;
        }

        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return $response;
        }

        try {
            app(DeviceService::class)->registerWebDevice($user, request());
        } catch (DeviceLimitReachedException $e) {
            Filament::auth()->logout();

            throw ValidationException::withMessages([
                'data.phone' => __('auth.device_limit'),
            ]);
        }

        return $response;
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
