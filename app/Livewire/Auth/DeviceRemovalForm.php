<?php

namespace App\Livewire\Auth;

use App\Models\Device;
use App\Models\User;
use App\Services\AuthService;
use App\Services\DeviceService;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * OTP self-service device removal (role-audit Medium-1 fix, option খ).
 * LOGIN-FREE flow for a user locked out by the 3-device limit:
 * phone → OTP → device list → remove one → log in again.
 *
 * Reuses the existing SEC-01 OTP system (AuthService::requestOtp /
 * verifyOtp, purpose `device_removal`): bcrypt-hashed code, 10-min TTL,
 * max 5 attempts, single-use. OTP verification here does NOT mark the
 * phone verified (verifyOtp $markPhoneVerified=false) — proving phone
 * ownership to remove a device must not change verification state.
 *
 * Security: the OTP request replies "OTP sent" whether or not the
 * account exists, so phone numbers cannot be enumerated; the device
 * list is only shown after a correct OTP. Removal is scoped to the
 * verified phone's own devices (and re-checked against the
 * server-side session flag).
 */
class DeviceRemovalForm extends Component
{
    public string $phone = '';

    public string $code = '';

    public bool $otpSent = false;

    public bool $verified = false;

    public string $verifiedPhone = '';

    /** Status line shown after send / removal (Livewire-safe, not a flash). */
    public string $statusMessage = '';

    /** Locale captured at mount (page render runs inside the {locale} group). */
    public string $locale = 'bn';

    public function mount(?string $locale = null): void
    {
        $this->locale = $locale ?? app()->getLocale();
    }

    public function send(AuthService $auth): void
    {
        $this->validate([
            'phone' => ['required', 'string', 'regex:/^01[3-9]\d{8}$/'],
        ]);

        // Same throttling as OtpForm::resend (5/hour per phone+IP).
        $key = 'device-removal-send:'.$this->phone.'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('phone', __('auth.otp_throttled', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }
        RateLimiter::hit($key, 3600);

        AuthService::forgetDevCode();

        // Only issue a code if the account exists; the reply is the
        // same either way so phone numbers cannot be enumerated.
        if (User::where('phone', $this->phone)->exists()) {
            $code = $auth->requestOtp($this->phone, AuthService::OTP_PURPOSE_DEVICE);
            AuthService::rememberDevCode($code);
        }

        $this->otpSent = true;
        $this->statusMessage = __('auth.otp_sent');
    }

    public function verify(AuthService $auth): void
    {
        $this->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'digits:6'],
        ]);

        // Same throttling as OtpForm::verify (5/min per phone+IP), on
        // top of the service's 5-attempts-per-code lockout.
        $key = 'device-removal-verify:'.$this->phone.'|'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('code', __('auth.otp_throttled', ['seconds' => RateLimiter::availableIn($key)]));

            return;
        }
        RateLimiter::hit($key, 60);

        if (! $auth->verifyOtp($this->phone, $this->code, AuthService::OTP_PURPOSE_DEVICE, false)) {
            $this->addError('code', __('auth.otp_invalid'));

            return;
        }

        AuthService::forgetDevCode();

        $this->verified = true;
        $this->verifiedPhone = $this->phone;
        $this->statusMessage = '';
        session(['device_removal_verified_phone' => $this->phone]);
    }

    public function removeDevice(int $deviceId): void
    {
        // Authoritative check: the verified phone from the server-side
        // session must match this component's verified phone.
        $phone = session('device_removal_verified_phone', $this->verifiedPhone);

        if (! $this->verified || $phone === '' || $phone !== $this->verifiedPhone) {
            $this->addError('phone', __('auth.otp_invalid'));

            return;
        }

        $user = User::where('phone', $phone)->first();

        if ($user === null) {
            return;
        }

        // Scoped to the verified user's own devices — another user's
        // device id is a 404 here, never a removal.
        $device = Device::where('user_id', $user->id)->findOrFail($deviceId);

        app(DeviceService::class)->revokeDevice($device);

        $this->statusMessage = __('auth.device_removed');
    }

    public function render()
    {
        $devices = collect();

        if ($this->verified && $this->verifiedPhone !== '') {
            $user = User::where('phone', $this->verifiedPhone)->first();

            if ($user !== null) {
                $devices = app(DeviceService::class)->devicesFor($user);
            }
        }

        return view('livewire.auth.device-removal-form', ['devices' => $devices]);
    }
}
