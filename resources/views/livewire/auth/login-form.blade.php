<form wire:submit="login">
    <div class="form-field">
        <label for="phone">{{ __('auth.phone') }}</label>
        <input type="tel" id="phone" wire:model="phone" placeholder="{{ __('auth.phone_placeholder') }}" autocomplete="username" required>
        @error('phone') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label for="password">{{ __('auth.password') }}</label>
        <input type="password" id="password" wire:model="password" autocomplete="current-password" required>
        @error('password') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label>
            <input type="checkbox" wire:model="remember" style="width:auto">
            {{ __('auth.remember_me') }}
        </label>
    </div>

    <button type="submit" class="btn">{{ __('auth.login_button') }}</button>

    <p>
        <a href="{{ route('password.forgot', ['locale' => app()->getLocale()]) }}">{{ __('auth.forgot_password') }}</a>
    </p>
    <p>
        {{ __('auth.no_account') }}
        <a href="{{ route('register', ['locale' => app()->getLocale()]) }}">{{ __('auth.register_link') }}</a>
    </p>
</form>
