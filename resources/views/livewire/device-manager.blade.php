<div>
  <p>{{ __('devices.used_of_limit', ['used' => $used, 'limit' => $limit]) }}</p>
  @if($devices->isEmpty())
    <p>{{ __('devices.none') }}</p>
  @else
    <ul>
      @foreach($devices as $device)
        <li>
          <strong>{{ $device->device_name }}</strong>
          <span>{{ $device->device_type }}</span>
          @if($device->platform) <span>{{ $device->platform }}</span> @endif
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
