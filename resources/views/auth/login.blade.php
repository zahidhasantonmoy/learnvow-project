@extends('layouts.guest')

@section('title', __('auth.login_title'))

@section('content')
    <h1 class="serif">{{ __('auth.login_title') }}</h1>
    @livewire('auth.login-form')
@endsection
