<?php

namespace App\Livewire\Auth;

use App\Services\AuthService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ResetPasswordForm extends Component
{
    public string $phone = '';

    public string $code = '';

    public string $password = '';

    public string $password_confirmation = '';

    /** Locale captured at mount (page render runs inside the {locale} group). */
    public string $locale = 'bn';

    public function mount(?string $locale = null): void
    {
        $this->locale = $locale ?? app()->getLocale();
        $this->phone = (string) session('otp_phone', '');
    }

    public function resetPassword(AuthService $auth)
    {
        $validated = $this->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // SEC-03: throttle reset attempts (5/min per phone+IP).
        $key = 'otp-reset:'.$validated['phone'].'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', __('auth.otp_throttled', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }
        RateLimiter::hit($key, 60);

        if (! $auth->resetPassword($validated['phone'], $validated['code'], $validated['password'])) {
            $this->addError('code', __('auth.otp_invalid'));

            return;
        }

        session()->forget('otp_phone');
        AuthService::forgetDevCode();

        return redirect()
            ->route('login', ['locale' => $this->locale])
            ->with('status', __('auth.password_reset'));
    }

    public function render()
    {
        return view('livewire.auth.reset-password-form');
    }
}
