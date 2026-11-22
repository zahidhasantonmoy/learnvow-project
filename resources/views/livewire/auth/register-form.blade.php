<form wire:submit="register">
    <div class="form-field">
        <label for="name">{{ __('auth.name') }}</label>
        <input type="text" id="name" wire:model="name" autocomplete="name" required>
        @error('name') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label for="phone">{{ __('auth.phone') }}</label>
        <input type="tel" id="phone" wire:model="phone" placeholder="{{ __('auth.phone_placeholder') }}" autocomplete="username" required>
        @error('phone') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label for="password">{{ __('auth.password') }}</label>
        <input type="password" id="password" wire:model="password" autocomplete="new-password" required>
        @error('password') <span class="error">{{ $message }}</span> @enderror
    </div>

    <div class="form-field">
        <label for="password_confirmation">{{ __('auth.password_confirmation') }}</label>
        <input type="password" id="password_confirmation" wire:model="password_confirmation" autocomplete="new-password" required>
    </div>

    <button type="submit" class="btn">{{ __('auth.register_button') }}</button>

    <p>
        {{ __('auth.have_account') }}
        <a href="{{ route('login', ['locale' => app()->getLocale()]) }}">{{ __('auth.login_link') }}</a>
    </p>
</form>
