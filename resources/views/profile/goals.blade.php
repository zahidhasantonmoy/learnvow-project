@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('goals.title') }}</h1>
  <p>{{ __('goals.intro') }}</p>
  @if(session('status')) <p>{{ session('status') }}</p> @endif
  @livewire('goal-tracker')
@endsection
