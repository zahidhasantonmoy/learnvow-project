<?php

namespace App\Http\Controllers\Api;

use App\Models\Device;
use App\Services\DeviceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** My devices (spec §1.3, §5.3). */
class DeviceController extends ApiController
{
    public function index(Request $request, DeviceService $devices)
    {
        $currentTokenId = $request->user()->currentAccessToken()?->id;

        return $this->ok([
            'limit' => $devices->deviceLimit(),
            'used' => $devices->devicesFor($request->user())->count(),
            'devices' => $devices->devicesFor($request->user())
                ->map(fn (Device $d) => $this->deviceArray($d, $currentTokenId))->values(),
        ]);
    }

    public function destroy(Request $request, Device $device, DeviceService $devices)
    {
        Gate::authorize('delete', $device);

        $currentTokenId = $request->user()->currentAccessToken()?->id;
        if ($currentTokenId !== null && (int) $device->api_token_id === $currentTokenId) {
            return $this->fail(__('api.device_current_cannot_remove'), 422);
        }

        $devices->revokeDevice($device);

        return $this->ok(['deleted' => true]);
    }
}
