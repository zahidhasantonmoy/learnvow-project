<?php

namespace App\Exceptions;

use App\Models\Device;
use Exception;
use Illuminate\Support\Collection;

/** Thrown when a new login would exceed the device limit (spec §5.2). */
class DeviceLimitReachedException extends Exception
{
    /** @param Collection<int, Device> $devices */
    public function __construct(public Collection $devices)
    {
        parent::__construct('Device limit reached.');
    }
}
