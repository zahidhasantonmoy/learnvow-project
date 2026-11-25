@extends('layouts.guest')

@section('title', __('auth.device_removal_title'))

@section('content')
    <h1 class="serif">{{ __('auth.device_removal_title') }}</h1>
    <p>{{ __('auth.device_removal_description') }}</p>
    @livewire('auth.device-removal-form')
@endsection
