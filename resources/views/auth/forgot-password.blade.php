@extends('layouts.guest')

@section('title', __('auth.forgot_title'))

@section('content')
    <h1 class="serif">{{ __('auth.forgot_title') }}</h1>
    @livewire('auth.forgot-password-form')
@endsection
