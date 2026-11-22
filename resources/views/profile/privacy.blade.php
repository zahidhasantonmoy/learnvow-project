@extends('layouts.app')

@section('title', __('auth.privacy_title'))

@section('content')
    <h1 class="serif">{{ __('auth.privacy_title') }}</h1>

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
    </dl>

    <p>
        <a href="{{ route('profile.edit', ['locale' => app()->getLocale()]) }}" class="btn">{{ __('auth.edit_profile') }}</a>
        <a href="{{ route('profile.delete', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('auth.delete_account') }}</a>
    </p>
@endsection
