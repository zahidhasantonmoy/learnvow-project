{{-- EB-02: branded 500 (was the stock Laravel page). Never prints the
     exception — no stack trace, paths or SQL reach the visitor. --}}
@extends('layouts.app')

@section('content')
  <h1 class="serif">500 — {{ __('auth.error_500_title') }}</h1>
  <p>{{ __('auth.error_500_body') }}</p>
  <p><a class="btn" href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('auth.back_home') }}</a></p>
@endsection
