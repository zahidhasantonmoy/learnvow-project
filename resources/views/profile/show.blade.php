@extends('layouts.app')

@section('title', __('auth.profile_title'))

@section('content')
    <h1 class="serif">{{ __('auth.profile_title') }}</h1>

    <dl>
        <dt>{{ __('auth.name') }}</dt>
        <dd>{{ auth()->user()->name }}</dd>

        <dt>{{ __('auth.phone') }}</dt>
        <dd>{{ auth()->user()->phone }}</dd>

        <dt>{{ __('auth.email') }}</dt>
        <dd>{{ auth()->user()->email ?? '—' }}</dd>

        <dt>{{ __('auth.locale') }}</dt>
        <dd>{{ auth()->user()->locale }}</dd>

        <dt>{{ __('auth.country') }}</dt>
        <dd>{{ auth()->user()->country }}</dd>

        <dt>{{ __('auth.status') }}</dt>
        <dd>{{ auth()->user()->status }}</dd>

        <dt>{{ __('auth.roles') }}</dt>
        <dd>{{ auth()->user()->getRoleNames()->implode(', ') }}</dd>

        <dt>{{ __('auth.phone') }}</dt>
        <dd>{{ auth()->user()->phone_verified_at ? __('auth.phone_verified') : __('auth.phone_not_verified') }}</dd>

        <dt>{{ __('auth.member_since') }}</dt>
        <dd>{{ auth()->user()->created_at?->format('Y-m-d') }}</dd>
    </dl>

    <p>
        <a href="{{ route('profile.edit', ['locale' => app()->getLocale()]) }}" class="btn">{{ __('auth.edit_profile') }}</a>
        <a href="{{ route('profile.privacy', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('auth.privacy_choices') }}</a>
    </p>
@endsection
