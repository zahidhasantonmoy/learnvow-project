<?php

namespace App\Livewire;

use App\Services\DeviceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Devices list + removal (§4.1/§5.3). Revocation belongs to Builder B's
 * DeviceService — used when it exists, else the row is deleted
 * directly (own devices only). Current-device marking needs
 * Builder B's identification (§5.1) and is not guessed here.
 */
class DeviceManager extends Component
{
    public function revoke(int $deviceId): void
    {
        $user = auth()->user();

        if (class_exists(DeviceService::class) && class_exists(\App\Models\Device::class)) {
            $device = \App\Models\Device::where('user_id', $user->id)->findOrFail($deviceId);
            app(DeviceService::class)->revokeDevice($device);
            session()->flash('status', __('devices.removed'));
            return;
        }

        if (Schema::hasTable('devices')) {
            $deleted = DB::table('devices')->where('user_id', $user->id)->where('id', $deviceId)->delete();
            abort_if($deleted === 0, 404);
            session()->flash('status', __('devices.removed'));
        }
    }

    public function render()
    {
        $devices = Schema::hasTable('devices')
            ? DB::table('devices')->where('user_id', auth()->id())->orderByDesc('last_active_at')->get()
            : collect();

        $limit = 3;
        if (Schema::hasTable('settings')) {
            $value = DB::table('settings')->where('key', 'protection.device_limit')->value('value');
            if ($value !== null && $value !== '') {
                $limit = max(1, (int) $value);
            }
        }

        return view('livewire.device-manager', [
            'devices' => $devices,
            'limit' => $limit,
            'used' => count($devices),
        ]);
    }
}
