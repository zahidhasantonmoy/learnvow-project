<?php

namespace App\Livewire\Auth;

use App\Services\AuthService;
use Livewire\Component;

class RegisterForm extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** Locale captured at mount (page render runs inside the {locale} group). */
    public string $locale = 'bn';

    public function mount(?string $locale = null): void
    {
        $this->locale = $locale ?? app()->getLocale();
    }

    public function register(AuthService $auth)
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/', 'unique:users,phone'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Locale comes from the URL prefix (A5).
        $validated['locale'] = $this->locale;

        $user = $auth->register($validated);

        // N1 (re-QA 2026-11-24): do NOT log the user in here. The OTP
        // page lives under `guest` middleware, so a logged-in user is
        // bounced home and the OTP step was unreachable — new accounts
        // ended up fully usable with phone_verified_at NULL. The user
        // stays a guest until the code is verified; OtpForm::verify()
        // logs them in after a correct code.

        // New accounts verify their phone by OTP next. Issue the code
        // now (SEC-01: previously no code was ever issued for this flow,
        // so the OTP page had nothing to verify against).
        $code = $auth->requestOtp($user->phone, AuthService::OTP_PURPOSE_VERIFY);
        AuthService::rememberDevCode($code);

        session(['otp_phone' => $user->phone]);

        return redirect()->route('otp', ['locale' => $this->locale]);
    }

    public function render()
    {
        return view('livewire.auth.register-form');
    }
}
