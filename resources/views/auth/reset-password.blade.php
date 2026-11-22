@extends('layouts.guest')

@section('title', __('auth.reset_title'))

@section('content')
    <h1 class="serif">{{ __('auth.reset_title') }}</h1>
    @livewire('auth.reset-password-form')
@endsection
