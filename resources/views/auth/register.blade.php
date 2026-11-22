@extends('layouts.guest')

@section('title', __('auth.register_title'))

@section('content')
    <h1 class="serif">{{ __('auth.register_title') }}</h1>
    @livewire('auth.register-form')
@endsection
