<?php

namespace App\Policies;

use App\Models\Device;
use App\Models\User;

/** Own devices only (spec §0.9, §5.3). */
class DevicePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Device $device): bool
    {
        return (int) $device->user_id === (int) $user->id;
    }

    public function delete(User $user, Device $device): bool
    {
        return (int) $device->user_id === (int) $user->id;
    }
}
