<?php

namespace App\Services;

use App\Exceptions\DeviceLimitReachedException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Auth logic: login / registration / OTP verification (spec §6).
 * Business logic lives here, not in controllers or Livewire components.
 * OTP is mocked in local dev (A3): any 6 digits are accepted and the code
 * is written to the log. Real SMS arrives in Phase 2.
 */
class AuthService
{
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
     * "Send" an OTP to a phone (mocked in dev, A3). Returns the code so the
     * dev log / tests can see it; production SMS replaces this in Phase 2.
     */
    public function requestOtp(string $phone): string
    {
        $code = (string) random_int(100000, 999999);

        Log::info('LearnVow OTP (mocked, dev only)', ['phone' => $phone, 'code' => $code]);

        return $code;
    }

    /**
     * Verify a 6-digit OTP (mocked in dev, A3): any 6 digits are accepted.
     * On success the user's phone is marked verified.
     */
    public function verifyOtp(string $phone, string $code): bool
    {
        if (! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $user = User::where('phone', $phone)->first();

        if ($user === null) {
            return false;
        }

        // Mocked verification (A3): the code itself is not checked in dev.
        Log::info('LearnVow OTP verified (mocked, dev only)', ['phone' => $phone, 'code' => $code]);

        if ($user->phone_verified_at === null) {
            $user->forceFill(['phone_verified_at' => now()])->save();
        }

        return true;
    }

    /**
     * Reset a user's password after OTP verification.
     */
    public function resetPassword(string $phone, string $code, string $password): bool
    {
        if (! $this->verifyOtp($phone, $code)) {
            return false;
        }

        $user = User::where('phone', $phone)->first();

        if ($user === null) {
            return false;
        }

        $user->forceFill(['password' => Hash::make($password)])->save();

        return true;
    }
}
