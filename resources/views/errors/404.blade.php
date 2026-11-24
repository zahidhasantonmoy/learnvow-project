{{-- EB-02: branded 404 (was the stock Laravel page). --}}
@extends('layouts.app')

@section('content')
  <h1 class="serif">404 — {{ __('auth.error_404_title') }}</h1>
  <p>{{ __('auth.error_404_body') }}</p>
  <p><a class="btn" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('auth.back_home') }}</a></p>
@endsection
