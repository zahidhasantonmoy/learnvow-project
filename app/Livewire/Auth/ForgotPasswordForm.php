<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Services\AuthService;
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

        // Only send a code if the account exists; the reply is the same
        // either way so phone numbers cannot be enumerated.
        if (User::where('phone', $this->phone)->exists()) {
            $auth->requestOtp($this->phone);
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
