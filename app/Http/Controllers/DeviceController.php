<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\User;
use App\Services\DeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Devices screen (§4.1/§5.1). The screen only — DeviceService and
 * the Device model are Builder B's. Everything here is guarded so
 * the screen renders (empty list) until they land.
 */
class DeviceController extends Controller
{
    use BuildsSeo;

    public function index(Request $request, string $locale)
    {
        $user = $request->user();
        $limit = $this->deviceLimit();
        $devices = $this->devicesFor($user);

        return view('profile.devices', [
            'devices' => $devices,
            'limit' => $limit,
            'used' => count($devices),
            'remaining' => max(0, $limit - count($devices)),
            'seo' => $this->seo(__('devices.title'), null, ['noindex' => true]),
        ]);
    }

    public function destroy(Request $request, string $locale, $device)
    {
        $user = $request->user();

        // Builder B's service owns revocation (token + FCM cleanup).
        if (class_exists(DeviceService::class) && class_exists(\App\Models\Device::class)) {
            $model = \App\Models\Device::where('user_id', $user->id)->findOrFail($device);
            app(DeviceService::class)->revokeDevice($model);

            return back()->with('status', __('devices.removed'));
        }

        // Fallback until Builder B lands: delete the row, own devices only.
        if (Schema::hasTable('devices')) {
            $deleted = DB::table('devices')->where('user_id', $user->id)->where('id', $device)->delete();
            abort_if($deleted === 0, 404);
            $this->auditDeviceRemoval($user, (int) $device);

            return back()->with('status', __('devices.removed'));
        }

        abort(404);
    }

    /** Devices for the user, or an empty list until Builder B lands. */
    public function devicesFor(User $user): array
    {
        if (! Schema::hasTable('devices')) {
            return [];
        }

        return DB::table('devices')->where('user_id', $user->id)
            ->orderByDesc('last_active_at')->get()->all();
    }

    public function deviceLimit(): int
    {
        if (Schema::hasTable('settings')) {
            $value = DB::table('settings')->where('key', 'protection.device_limit')->value('value');
            if ($value !== null && $value !== '') {
                return max(1, (int) $value);
            }
        }

        return 3;
    }

    protected function auditDeviceRemoval(User $user, int $deviceId): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        DB::table('audit_logs')->insert([
            'actor_id' => $user->id,
            'action' => 'device.revoked',
            'subject_type' => 'devices',
            'subject_id' => $deviceId,
            'details' => json_encode(['message' => 'Removed device '.$deviceId]),
            'created_at' => now(),
        ]);
    }
}
