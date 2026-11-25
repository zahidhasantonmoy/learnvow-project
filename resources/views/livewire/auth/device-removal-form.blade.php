<div>
    @if($statusMessage !== '')
        <p>{{ $statusMessage }}</p>
    @endif

    @if(! $verified)
        <form wire:submit="send">
            <div class="form-field">
                <label for="phone">{{ __('auth.phone') }}</label>
                <input type="tel" id="phone" wire:model="phone" placeholder="{{ __('auth.phone_placeholder') }}" autocomplete="username" required>
                @error('phone') <span class="error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn">{{ $otpSent ? __('auth.resend_otp') : __('auth.send_otp_button') }}</button>
        </form>

        @if($otpSent)
            <form wire:submit="verify">
                <div class="form-field">
                    <label for="code">{{ __('auth.otp_code') }}</label>
                    <input type="text" id="code" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required>
                    @error('code') <span class="error">{{ $message }}</span> @enderror
                </div>

                <p>{{ __('auth.otp_hint') }}</p>

                @if(app()->environment('local') && session('dev_otp_code'))
                    <p><strong>{{ __('auth.dev_otp_code') }} {{ session('dev_otp_code') }}</strong></p>
                @endif

                <button type="submit" class="btn">{{ __('auth.verify_button') }}</button>
            </form>
        @endif
    @else
        <p>{{ __('auth.device_removal_devices_hint') }}</p>

        @if($devices->isEmpty())
            <p>{{ __('auth.device_removal_no_devices') }}</p>
        @else
            <ul>
                @foreach($devices as $device)
                    <li>
                        <strong>{{ $device->device_name }}</strong>
                        <span>({{ $device->device_type === 'app' ? __('devices.app') : __('devices.web') }})</span>
                        @if($device->platform && ! str_contains((string) $device->device_name, (string) $device->platform)) <span>{{ $device->platform }}</span> @endif
                        @if($device->last_active_at) <span>{{ $device->last_active_at }}</span> @endif
                        <button type="button" wire:click="removeDevice({{ $device->id }})" onclick="return confirm('{{ __('devices.remove_confirm') }}')">{{ __('devices.remove') }}</button>
                    </li>
                @endforeach
            </ul>
        @endif

        <p>
            <a href="{{ route('login', ['locale' => $locale]) }}">{{ __('auth.login_link') }}</a>
        </p>
    @endif

    <p>
        <a href="{{ route('login', ['locale' => $locale]) }}">{{ __('auth.back_to_login') }}</a>
    </p>
</div>
