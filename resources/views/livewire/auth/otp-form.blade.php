<form wire:submit="verify">
    <div class="form-field">
        <label for="phone">{{ __('auth.phone') }}</label>
        <input type="tel" id="phone" wire:model="phone" placeholder="{{ __('auth.phone_placeholder') }}" autocomplete="username" required>
        @error('phone') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label for="code">{{ __('auth.otp_code') }}</label>
        <input type="text" id="code" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required>
        @error('code') <span class="error">{{ $message }}</span> @enderror
    </div>

    <p>{{ __('auth.otp_hint') }}</p>

    <button type="submit" class="btn">{{ __('auth.verify_button') }}</button>
    <button type="button" class="btn btn-secondary" wire:click="resend">{{ __('auth.resend_otp') }}</button>
</form>
