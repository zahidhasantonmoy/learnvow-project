@extends('layouts.guest')

@section('title', __('auth.otp_title'))

@section('content')
    <h1 class="serif">{{ __('auth.otp_title') }}</h1>
    @livewire('auth.otp-form')
@endsection
