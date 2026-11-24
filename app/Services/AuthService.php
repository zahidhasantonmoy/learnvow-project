<?php

namespace App\Services;

use App\Exceptions\DeviceLimitReachedException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Auth logic: login / registration / OTP verification (spec §6).
 * Business logic lives here, not in controllers or Livewire components.
 *
 * OTP is REAL (audit fix SEC-01, 2026-11-24): the issued code is stored
 * only as a bcrypt hash in the cache, keyed by phone + purpose, with a
 * 10-minute expiry, max 5 verification attempts, and single-use
 * (invalidated on success or when the attempt limit is hit). The code
 * itself is NEVER logged (SEC-07) — only a masked phone + purpose.
 * No SMS provider is wired yet, so in the local dev environment the
 * issued code is shown on the OTP page (dev-only affordance, see
 * OtpForm / ForgotPasswordForm / RegisterForm); it is never shown
 * outside `local`.
 */
class AuthService
{
    /** OTP lifetime in minutes. */
    public const OTP_TTL_MINUTES = 10;

    /** Max failed verification attempts before the code is invalidated. */
    public const OTP_MAX_ATTEMPTS = 5;

    /** OTP purpose: phone verification after registration. */
    public const OTP_PURPOSE_VERIFY = 'verify';

    /** OTP purpose: password reset. */
    public const OTP_PURPOSE_RESET = 'password_reset';

    /**
     * Attempt a phone + password login.
     *
     * @return array{ok: bool, user?: User, error?: string}
     */
    /**
     * @return array{ok: bool, user?: User, error?: string, devices?: \Illuminate\Support\Collection}
     */
    public function login(string $phone, string $password, bool $remember = false, bool $registerDevice = true): array
    {
        $user = User::where('phone', $phone)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            return ['ok' => false, 'error' => 'credentials'];
        }

        if (in_array($user->status, User::blockedStatuses(), true)) {
            return ['ok' => false, 'error' => 'blocked', 'user' => $user];
        }

        Auth::login($user, $remember);

        // Phase 4 (§5.2): register the web device after login. A 4th
        // device blocks the login (session is dropped again). API logins
        // pass $registerDevice=false and register the API device instead.
        if ($registerDevice) {
            try {
                app(DeviceService::class)->registerWebDevice($user, request());
            } catch (DeviceLimitReachedException $e) {
                Auth::logout();

                return ['ok' => false, 'error' => 'device_limit_reached', 'user' => $user, 'devices' => $e->devices];
            }
        }

        return ['ok' => true, 'user' => $user];
    }

    /**
     * Register a new Reader account. The caller validates the input;
     * this method only creates the user and assigns the reader role.
     */
    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'password' => Hash::make($data['password']),
            'locale' => $data['locale'] ?? 'bn',
            'country' => $data['country'] ?? 'BD',
            'status' => 'active',
        ]);

        $user->assignRole('reader');

        return $user;
    }

    /**
     * Issue an OTP for a phone + purpose. Only the bcrypt hash is stored
     * (cache, 10 min TTL); the plain code is returned so the caller can
     * hand it to the SMS sender — in local dev it is shown on the OTP
     * page instead, because no SMS provider is wired yet. The code is
     * never logged.
     */
    public function requestOtp(string $phone, string $purpose = self::OTP_PURPOSE_VERIFY): string
    {
        $code = (string) random_int(100000, 999999);

        Cache::put($this->otpKey($phone, $purpose), [
            'hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(self::OTP_TTL_MINUTES)->timestamp,
        ], now()->addMinutes(self::OTP_TTL_MINUTES));

        Log::info('LearnVow OTP issued', [
            'phone' => $this->maskPhone($phone),
            'purpose' => $purpose,
        ]);

        return $code;
    }

    /**
     * Verify a 6-digit OTP against the stored hash. Single-use: the code
     * is invalidated on success and after OTP_MAX_ATTEMPTS failures.
     * On success the user's phone is marked verified.
     */
    public function verifyOtp(string $phone, string $code, string $purpose = self::OTP_PURPOSE_VERIFY): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $user = User::where('phone', $phone)->first();

        if ($user === null) {
            return false;
        }

        $key = $this->otpKey($phone, $purpose);
        $record = Cache::get($key);

        if (! is_array($record) || ! isset($record['hash'])) {
            return false;
        }

        if ((int) ($record['attempts'] ?? 0) >= self::OTP_MAX_ATTEMPTS) {
            Cache::forget($key);

            return false;
        }

        if (! Hash::check($code, $record['hash'])) {
            $record['attempts'] = (int) ($record['attempts'] ?? 0) + 1;

            if ($record['attempts'] >= self::OTP_MAX_ATTEMPTS) {
                Cache::forget($key);
                Log::warning('LearnVow OTP attempts exhausted', [
                    'phone' => $this->maskPhone($phone),
                    'purpose' => $purpose,
                ]);
            } else {
                // Preserve the original expiry — a failed attempt must
                // not extend the code's lifetime.
                $ttl = max(1, (int) ($record['expires_at'] ?? 0) - now()->timestamp);
                Cache::put($key, $record, now()->addSeconds($ttl));
            }

            return false;
        }

        // Single-use: the code dies on success.
        Cache::forget($key);

        Log::info('LearnVow OTP verified', [
            'phone' => $this->maskPhone($phone),
            'purpose' => $purpose,
        ]);

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return true;
    }

    /**
     * Reset a user's password after OTP verification (purpose
     * `password_reset` — a code issued for phone verification does not
     * reset a password, and vice versa).
     */
    public function resetPassword(string $phone, string $code, string $password): bool
    {
        if (! $this->verifyOtp($phone, $code, self::OTP_PURPOSE_RESET)) {
            return false;
        }

        $user = User::where('phone', $phone)->first();

        if ($user === null) {
            return false;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        return true;
    }

    /** Cache key for a phone + purpose OTP record. */
    protected function otpKey(string $phone, string $purpose): string
    {
        return 'otp:'.$purpose.':'.$phone;
    }

    /**
     * Dev-only affordance (SEC-01 note): no SMS provider is wired yet,
     * so in the `local` environment the issued code is kept in the
     * session and shown on the OTP page. NEVER active outside `local`
     * — production/staging/testing never see the code.
     */
    public static function rememberDevCode(string $code): void
    {
        if (app()->environment('local')) {
            session(['dev_otp_code' => $code]);
        }
    }

    public static function forgetDevCode(): void
    {
        session()->forget('dev_otp_code');
    }

    /** Mask a phone for logs (never log a full phone next to OTP events). */
    protected function maskPhone(string $phone): string
    {
        return strlen($phone) > 3 ? '··'.substr($phone, -3) : '···';
    }
}
