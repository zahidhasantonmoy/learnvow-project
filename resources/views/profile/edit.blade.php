@extends('layouts.app')

@section('title', __('auth.edit_profile_title'))

@section('content')
    <h1 class="serif">{{ __('auth.edit_profile_title') }}</h1>

    <form method="POST" action="{{ route('profile.edit', ['locale' => app()->getLocale()]) }}">
        @csrf

        <div class="form-field">
            <label for="name">{{ __('auth.name') }}</label>
            <input type="text" id="name" name="name" value="{{ old('name', auth()->user()->name) }}" required>
            @error('name') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="email">{{ __('auth.email') }}</label>
            <input type="email" id="email" name="email" value="{{ old('email', auth()->user()->email) }}">
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="locale">{{ __('auth.locale') }}</label>
            <select id="locale" name="locale">
                <option value="bn" @selected(old('locale', auth()->user()->locale) === 'bn')>বাংলা</option>
                <option value="en" @selected(old('locale', auth()->user()->locale) === 'en')>English</option>
            </select>
            @error('locale') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="country">{{ __('auth.country') }}</label>
            <input type="text" id="country" name="country" value="{{ old('country', auth()->user()->country) }}" maxlength="2" required>
            @error('country') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="current_password">{{ __('auth.current_password') }}</label>
            <input type="password" id="current_password" name="current_password" autocomplete="current-password">
            @error('current_password') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="password">{{ __('auth.new_password') }}</label>
            <input type="password" id="password" name="password" autocomplete="new-password">
            @error('password') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="form-field">
            <label for="password_confirmation">{{ __('auth.password_confirmation') }}</label>
            <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
        </div>

        <button type="submit" class="btn">{{ __('auth.save_changes') }}</button>
    </form>

    <p>
        <a href="{{ route('profile.delete', ['locale' => app()->getLocale()]) }}">{{ __('auth.delete_account') }}</a>
    </p>
@endsection
