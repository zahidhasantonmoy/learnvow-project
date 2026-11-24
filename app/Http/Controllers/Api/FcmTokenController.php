<?php

namespace App\Http\Controllers\Api;

use App\Services\DeviceService;
use App\Services\FcmService;
use Illuminate\Http\Request;

/** FCM token register / revoke (spec §8.2). */
class FcmTokenController extends ApiController
{
    public function store(Request $request, FcmService $fcm, DeviceService $devices)
    {
        $data = $request->validate([
            'token' => 'required|string|max:1000',
            'platform' => 'required|in:android,ios,web',
            'device_id' => 'nullable|exists:devices,id',
        ]);

        $device = null;
        if (! empty($data['device_id'])) {
            $device = $devices->devicesFor($request->user())->firstWhere('id', (int) $data['device_id']);
            if ($device === null) {
                abort(403);
            }
        }

        $row = $fcm->registerToken($request->user(), $data['token'], $data['platform'], $device);

        return $this->ok([
            'id' => $row->id, 'platform' => $row->platform,
            'token_tail' => $row->maskedToken(),
        ], 201);
    }

    public function destroy(Request $request, FcmService $fcm)
    {
        $data = $request->validate(['token' => 'nullable|string|max:1000']);
        $fcm->revokeToken($request->user(), $data['token'] ?? null);

        return $this->ok(['deleted' => true]);
    }
}
