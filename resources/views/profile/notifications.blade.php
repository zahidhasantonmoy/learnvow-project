@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.notification_settings_title') }}</h1>
  <p>{{ __('author.notification_settings_note') }}</p>
  <form method="POST" action="{{ route('profile.notifications.update', ['locale' => app()->getLocale()]) }}">
    @csrf
    @method('PUT')
    <p>
      <label><input type="checkbox" name="payouts" value="1" checked> {{ __('author.notification_payouts') }}</label>
    </p>
    <p>
      <label><input type="checkbox" name="approvals" value="1" checked> {{ __('author.notification_approvals') }}</label>
    </p>
    <p>
      <label><input type="checkbox" name="tickets" value="1" checked> {{ __('author.notification_tickets') }}</label>
    </p>
    <p>
      <label><input type="checkbox" name="announcements" value="1" checked> {{ __('author.notification_announcements') }}</label>
    </p>
    <p><button type="submit" class="btn">{{ __('author.save') }}</button></p>
  </form>
@endsection
