<div>
  <p>{{ __('devices.used_of_limit', ['used' => $used, 'limit' => $limit]) }}</p>
  @if($devices->isEmpty())
    <p>{{ __('devices.none') }}</p>
  @else
    <ul>
      @foreach($devices as $device)
        <li>
          {{-- UI-12: the name, type and platform used to repeat each other
               ("Chrome — Linux — Web web Chrome — Linux"). Show the name
               once, the type once, and the platform only when it adds
               something the name does not already say (old rows stored
               the platform inside the name). --}}
          <strong>{{ $device->device_name }}</strong>
          <span>({{ $device->device_type === 'app' ? __('devices.app') : __('devices.web') }})</span>
          @if($device->platform && ! str_contains((string) $device->device_name, (string) $device->platform)) <span>{{ $device->platform }}</span> @endif
          @if($device->last_active_at) <span>{{ $device->last_active_at }}</span> @endif
          @if($device->last_active_at && \Carbon\Carbon::parse($device->last_active_at)->lt(now()->subDays(90)))
            <span class="badge">{{ __('devices.inactive_90') }}</span>
          @endif
          <button type="button" wire:click="revoke({{ $device->id }})" onclick="return confirm('{{ __('devices.remove_confirm') }}')">{{ __('devices.remove') }}</button>
        </li>
      @endforeach
    </ul>
  @endif
</div>
