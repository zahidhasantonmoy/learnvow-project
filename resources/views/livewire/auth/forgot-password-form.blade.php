<form wire:submit="send">
    <div class="form-field">
        <label for="phone">{{ __('auth.phone') }}</label>
        <input type="tel" id="phone" wire:model="phone" placeholder="{{ __('auth.phone_placeholder') }}" autocomplete="username" required>
        @error('phone') <span class="error">{{ $message }}</span> @enderror
    </div>

    <button type="submit" class="btn">{{ __('auth.send_otp_button') }}</button>

    <p>
        <a href="{{ route('login', ['locale' => app()->getLocale()]) }}">{{ __('auth.back_to_login') }}</a>
    </p>
</form>
