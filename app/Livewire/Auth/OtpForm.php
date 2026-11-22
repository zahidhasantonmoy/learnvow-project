<?php

namespace App\Livewire\Auth;

use App\Services\AuthService;
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

        if (! $auth->verifyOtp($this->phone, $this->code)) {
            $this->addError('code', __('auth.otp_invalid'));

            return;
        }

        session()->forget('otp_phone');

        return redirect()->to('/'.$this->locale);
    }

    public function resend(AuthService $auth): void
    {
        $this->validate(['phone' => ['required', 'string']]);

        $auth->requestOtp($this->phone);

        session()->flash('status', __('auth.otp_sent'));
    }

    public function render()
    {
        return view('livewire.auth.otp-form');
    }
}
