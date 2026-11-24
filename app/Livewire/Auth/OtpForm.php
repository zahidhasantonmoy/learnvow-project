<?php

namespace App\Livewire\Auth;

use App\Exceptions\DeviceLimitReachedException;
use App\Models\User;
use App\Services\AuthService;
use App\Services\DeviceService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class OtpForm extends Component
{
    public string $phone = '';

    public string $code = '';

    /** Locale captured at mount (page render runs inside the {locale} group). */
    public string $locale = 'bn';

    public function mount(?string $locale = null): void
    {
        $this->locale = $locale ?? app()->getLocale();
        $this->phone = (string) session('otp_phone', '');
    }

    public function verify(AuthService $auth)
    {
        $this->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        // SEC-03: throttle verification attempts (5/min per phone+IP),
        // on top of the service's 5-attempts-per-code lockout (SEC-01).
        $key = 'otp-verify:'.$this->phone.'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', __('auth.otp_throttled', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }
        RateLimiter::hit($key, 60);

        if (! $auth->verifyOtp($this->phone, $this->code)) {
            $this->addError('code', __('auth.otp_invalid'));

            return;
        }

        session()->forget('otp_phone');
        AuthService::forgetDevCode();

        // N1 (re-QA 2026-11-24): registration no longer logs the user in
        // before OTP, so the login happens here, only after the phone is
        // verified. (Existing accounts are unaffected — they log in via
        // LoginForm with password only; no verification is enforced on
        // them.) Register the web device like a normal login; a fresh
        // account has no devices, so the limit cannot trigger here, but
        // stay safe: on limit, drop the session and report login failure.
        $user = User::where('phone', $this->phone)->first();
        if ($user !== null && ! in_array($user->status, User::blockedStatuses(), true)) {
            Auth::login($user);
            session()->regenerate();

            try {
                app(DeviceService::class)->registerWebDevice($user, request());
            } catch (DeviceLimitReachedException $e) {
                Auth::logout();
                $this->addError('code', __('auth.login_failed'));

                return;
            }
        }

        return redirect()->to('/'.$this->locale);
    }

    public function resend(AuthService $auth): void
    {
        $this->validate(['phone' => ['required', 'string']]);

        // SEC-03: throttle resends (5/hour per phone+IP).
        $key = 'otp-resend:'.$this->phone.'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('phone', __('auth.otp_throttled', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }
        RateLimiter::hit($key, 3600);

        $code = $auth->requestOtp($this->phone);
        AuthService::rememberDevCode($code);

        session()->flash('status', __('auth.otp_sent'));
    }

    public function render()
    {
        return view('livewire.auth.otp-form');
    }
}
