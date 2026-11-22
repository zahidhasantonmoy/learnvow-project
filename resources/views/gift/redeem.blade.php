@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.gift_redeem_title') }}</h1>
  @guest
    <p><a href="{{ route('login', ['locale' => app()->getLocale()]) }}">{{ __('nav.login') }}</a></p>
  @endguest
  @auth
    <form method="POST" action="{{ route('gift.claim', ['locale' => app()->getLocale()]) }}">
      @csrf
      <label>{{ __('billing.gift_code') }}
        <input type="text" name="code" value="{{ $code }}" required>
      </label>
      @error('code')<p>{{ $message }}</p>@enderror
      <button type="submit" class="btn">{{ __('billing.gift_redeem') }}</button>
    </form>
  @endauth
@endsection
