<?php

namespace App\Services;

use App\Exceptions\DeviceLimitReachedException;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Device limit 3 (spec §5). One device = one login session-device pair:
 * web = User-Agent + IP hash, app = Sanctum token id (A10).
 */
class DeviceService
{
    public function deviceLimit(): int
    {
        $value = null;
        if (Schema::hasTable('settings')) {
            $value = DB::table('settings')->where('key', 'protection.device_limit')->value('value');
        }

        return $value === null || $value === '' ? 3 : max(1, (int) $value);
    }

    /** Active devices for a user, most recently active first. */
    public function devicesFor(User $user)
    {
        return Device::where('user_id', $user->id)->orderByDesc('last_active_at')->get();
    }

    /**
     * Register a web device after login. Same fingerprint → touch
     * last_active_at and continue. New device over the limit → throws
     * DeviceLimitReachedException (caller turns it into the §5.2 message).
     */
    public function registerWebDevice(User $user, Request $request): Device
    {
        $existing = Device::where('user_id', $user->id)
            ->where('device_type', 'web')
            ->where('user_agent', $request->userAgent())
            ->where('last_ip', $request->ip())
            ->first();

        if ($existing !== null) {
            $existing->update(['last_active_at' => now()]);

            return $existing->fresh();
        }

        $this->assertUnderLimit($user);

        return Device::create([
            'user_id' => $user->id,
            'device_name' => $this->webDeviceName($request->userAgent()),
            'device_type' => 'web',
            'platform' => $this->platformFromUserAgent($request->userAgent()),
            'last_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_active_at' => now(),
            'api_token_id' => null,
        ]);
    }

    /**
     * Register an app (API) device. Called after the Sanctum token is
     * issued so api_token_id can be stored. Same token id → touch only.
     */
    public function registerApiDevice(User $user, string $deviceName, ?int $apiTokenId = null): Device
    {
        if ($apiTokenId !== null) {
            $existing = Device::where('user_id', $user->id)
                ->where('api_token_id', $apiTokenId)->first();
            if ($existing !== null) {
                $existing->update(['last_active_at' => now()]);

                return $existing->fresh();
            }
        }

        $this->assertUnderLimit($user);

        return Device::create([
            'user_id' => $user->id,
            'device_name' => $deviceName,
            'device_type' => 'app',
            'platform' => null,
            'last_ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'last_active_at' => now(),
            'api_token_id' => $apiTokenId,
        ]);
    }

    private function assertUnderLimit(User $user): void
    {
        $count = Device::where('user_id', $user->id)->count();
        if ($count >= $this->deviceLimit()) {
            throw new DeviceLimitReachedException($this->devicesFor($user));
        }
    }

    /**
     * Revoke a device: delete its Sanctum token, its FCM tokens and the
     * row itself; audit-logged (spec §5.3). Web sessions are NOT killed
     * immediately — the session id is not stored (A10); the effect is on
     * the next login.
     */
    public function revokeDevice(Device $device): void
    {
        DB::transaction(function () use ($device) {
            if ($device->api_token_id !== null) {
                DB::table('personal_access_tokens')->where('id', $device->api_token_id)->delete();
            }
            if (Schema::hasTable('fcm_tokens')) {
                DB::table('fcm_tokens')->where('device_id', $device->id)->delete();
            }
            $device->delete();

            if (Schema::hasTable('audit_logs')) {
                AuditLog::create([
                    'actor_id' => $device->user_id,
                    'actor_role' => 'reader',
                    'action' => 'device.revoked',
                    'subject_type' => Device::class,
                    'subject_id' => $device->id,
                    'details' => ['device_name' => $device->device_name, 'device_type' => $device->device_type],
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function webDeviceName(?string $userAgent): string
    {
        // UI-12: the name used to be "$platform — Web" while the blade
        // also printed device_type ("web") and platform ("Chrome —
        // Linux") — rendering "Chrome — Linux — Web web Chrome — Linux".
        // The name is just the platform now; the type is shown once
        // by the devices list itself.
        $platform = $this->platformFromUserAgent($userAgent);

        return $platform !== '' ? $platform : 'Web browser';
    }

    public function platformFromUserAgent(?string $userAgent): string
    {
        $ua = (string) $userAgent;
        $browser = str_contains($ua, 'Chrome') ? 'Chrome'
            : (str_contains($ua, 'Firefox') ? 'Firefox'
            : (str_contains($ua, 'Safari') ? 'Safari' : 'Browser'));
        $os = str_contains($ua, 'Windows') ? 'Windows'
            : (str_contains($ua, 'Mac OS') ? 'macOS'
            : (str_contains($ua, 'Android') ? 'Android'
            : (str_contains($ua, 'Linux') ? 'Linux' : '')));

        return trim($browser.($os !== '' ? ' — '.$os : ''));
    }
}
