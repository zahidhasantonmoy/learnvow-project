<?php

namespace App\Livewire\Auth;

use App\Services\AuthService;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class LoginForm extends Component
{
    public string $phone = '';

    public string $password = '';

    public bool $remember = false;

    /** True when the last failure was the device limit (not a bad password). */
    public bool $deviceLimitReached = false;

    /** Locale captured at mount (page render runs inside the {locale} group). */
    public string $locale = 'bn';

    public function mount(?string $locale = null): void
    {
        $this->locale = $locale ?? app()->getLocale();
    }

    public function login(AuthService $auth)
    {
        $this->deviceLimitReached = false;

        $this->validate([
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $key = 'login:'.Str::lower($this->phone).'|'.request()->ip();

        // 5 wrong attempts → 1 minute lock (spec §3.2).
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('phone', __('auth.too_many_attempts', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }

        $result = $auth->login($this->phone, $this->password, $this->remember);

        if (! $result['ok']) {
            RateLimiter::hit($key, 60);

            // Medium-1 (a): the device limit is a distinct failure — it
            // only ever appears after a CORRECT password (AuthService
            // checks the password first), so naming it leaks nothing.
            // 'credentials' and 'blocked' keep the generic message.
            if (($result['error'] ?? null) === 'device_limit_reached') {
                $this->deviceLimitReached = true;
                $this->addError('phone', __('auth.device_limit'));

                return;
            }

            $this->addError('phone', __('auth.login_failed'));

            return;
        }

        RateLimiter::clear($key);
        session()->regenerate();

        return redirect()->intended('/'.$this->locale);
    }

    public function render()
    {
        return view('livewire.auth.login-form');
    }
}
