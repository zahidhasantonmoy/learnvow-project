@extends('layouts.app')

@section('title', __('auth.delete_title'))

@section('content')
    <h1 class="serif">{{ __('auth.delete_title') }}</h1>

    <div class="alert">{{ __('auth.delete_warning') }}</div>

    @if (auth()->user()->hasRole('super-admin'))
        <div class="alert">{{ __('auth.delete_super_admin_refused') }}</div>
    @else
        <form method="POST" action="{{ route('profile.delete', ['locale' => app()->getLocale()]) }}">
            @csrf

            <div class="form-field">
                <label for="password">{{ __('auth.delete_confirm') }}</label>
                <input type="password" id="password" name="password" autocomplete="current-password" required>
                @error('password') <span class="error">{{ $message }}</span> @enderror
            </div>

            <button type="submit" class="btn">{{ __('auth.delete_button') }}</button>
        </form>
    @endif
@endsection
