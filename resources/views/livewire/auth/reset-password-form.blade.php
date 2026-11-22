<form wire:submit="resetPassword">
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

    <div class="form-field">
        <label for="password">{{ __('auth.new_password') }}</label>
        <input type="password" id="password" wire:model="password" autocomplete="new-password" required>
        @error('password') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label for="password_confirmation">{{ __('auth.password_confirmation') }}</label>
        <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" required>
    </div>

    <button type="submit" class="btn">{{ __('auth.reset_button') }}</button>

    <p>
        <a href="{{ route('login', ['locale' => app()->getLocale()]) }}">{{ __('auth.back_to_login') }}</a>
    </p>
</form>
