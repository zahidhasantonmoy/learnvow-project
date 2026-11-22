<?php

namespace App\Livewire\Auth;

use App\Services\AuthService;
use Illuminate\Support\Facades\Auth;
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

        Auth::login($user);
        session()->regenerate();

        // New accounts verify their phone by OTP next.
        session(['otp_phone' => $user->phone]);

        return redirect()->route('otp', ['locale' => $this->locale]);
    }

    public function render()
    {
        return view('livewire.auth.register-form');
    }
}
