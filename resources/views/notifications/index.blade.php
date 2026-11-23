@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.notifications_title') }}</h1>
  @livewire('notification-list')
@endsection
