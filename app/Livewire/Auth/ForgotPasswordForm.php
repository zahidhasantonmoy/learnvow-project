<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class ForgotPasswordForm extends Component
{
    public string $phone = '';

    /** Locale captured at mount (page render runs inside the {locale} group). */
    public string $locale = 'bn';

    public function mount(?string $locale = null): void
    {
        $this->locale = $locale ?? app()->getLocale();
    }

    public function send(AuthService $auth)
    {
        $this->validate([
            'phone' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
        ]);

        // SEC-03: throttle OTP sends (5/hour per phone+IP) — once real
        // SMS is wired, unlimited sends would be SMS-cost abuse.
        $key = 'otp-send:'.$this->phone.'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('phone', __('auth.otp_throttled', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }
        RateLimiter::hit($key, 3600);

        // Only send a code if the account exists; the reply is the same
        // either way so phone numbers cannot be enumerated.
        if (User::where('phone', $this->phone)->exists()) {
            $code = $auth->requestOtp($this->phone, AuthService::OTP_PURPOSE_RESET);
            AuthService::rememberDevCode($code);
        }

        session(['otp_phone' => $this->phone]);

        return redirect()
            ->route('password.reset', ['locale' => $this->locale])
            ->with('status', __('auth.otp_sent'));
    }

    public function render()
    {
        return view('livewire.auth.forgot-password-form');
    }
}
