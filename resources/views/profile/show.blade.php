@extends('layouts.app')

@section('title', __('auth.profile_title'))

@section('content')
    <h1 class="serif">{{ __('auth.profile_title') }}</h1>

    <div class="card">
    <dl>
        <dt>{{ __('auth.name') }}</dt>
        <dd>{{ auth()->user()->name }}</dd>

        <dt>{{ __('auth.phone') }}</dt>
        <dd>{{ auth()->user()->phone }}</dd>

        <dt>{{ __('auth.email') }}</dt>
        <dd>{{ auth()->user()->email ?? '—' }}</dd>

        <dt>{{ __('auth.locale') }}</dt>
        {{-- UI-13: show a human language name, not the raw "bn" code. --}}
        <dd>{{ auth()->user()->locale === 'bn' ? __('auth.language_bangla') : __('auth.language_english') }}</dd>

        <dt>{{ __('auth.country') }}</dt>
        {{-- UI-13: show a human country name, not the raw "BD" code. --}}
        <dd>{{ auth()->user()->country === 'BD' ? __('auth.country_bangladesh') : (auth()->user()->country ?? '—') }}</dd>

        <dt>{{ __('auth.status') }}</dt>
        <dd>{{ auth()->user()->status }}</dd>

        <dt>{{ __('auth.roles') }}</dt>
        <dd>{{ auth()->user()->getRoleNames()->implode(', ') }}</dd>

        {{-- UI-13: the verification row had the same "Phone number" label
             as the phone row above — give it its own label. --}}
        <dt>{{ __('auth.phone_verification') }}</dt>
        <dd>{{ auth()->user()->phone_verified_at ? __('auth.phone_verified') : __('auth.phone_not_verified') }}</dd>

        <dt>{{ __('auth.member_since') }}</dt>
        <dd>{{ auth()->user()->created_at?->format('Y-m-d') }}</dd>
    </dl>

    <p>
        <a href="{{ route('profile.edit', ['locale' => app()->getLocale()]) }}" class="btn">{{ __('auth.edit_profile') }}</a>
        <a href="{{ route('profile.privacy', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('auth.privacy_choices') }}</a>
    </p>
    </div>

    <div class="card">
    {{-- UI-03: these Phase 4 screens had no inbound link anywhere — they
         were only reachable by typing the URL. Linked here (collections
         and vocabulary already have links elsewhere, so they are not
         duplicated). --}}
    <h2 class="serif">{{ __('auth.my_reading') }}</h2>
    <ul>
        <li><a href="{{ route('for-you', ['locale' => app()->getLocale()]) }}">{{ __('books.for_you_title') }}</a></li>
        <li><a href="{{ route('reading-goals.index', ['locale' => app()->getLocale()]) }}">{{ __('goals.title') }}</a></li>
        <li><a href="{{ route('badges.index', ['locale' => app()->getLocale()]) }}">{{ __('books.badges_title') }}</a></li>
        <li><a href="{{ route('preorders.index', ['locale' => app()->getLocale()]) }}">{{ __('books.preorders_title') }}</a></li>
        <li><a href="{{ route('books.request.create', ['locale' => app()->getLocale()]) }}">{{ __('books.request_book_title') }}</a></li>
        <li><a href="{{ route('profile.taste.edit', ['locale' => app()->getLocale()]) }}">{{ __('books.taste_title') }}</a></li>
        <li><a href="{{ route('profile.devices', ['locale' => app()->getLocale()]) }}">{{ __('devices.title') }}</a></li>
        <li><a href="{{ route('community.index', ['locale' => app()->getLocale()]) }}">{{ __('community.feed_title') }}</a></li>
        <li><a href="{{ route('clubs.index', ['locale' => app()->getLocale()]) }}">{{ __('community.clubs_title') }}</a></li>
    </ul>
    </div>
@endsection
