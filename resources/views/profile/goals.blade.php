@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('goals.title') }}</h1>
  <div class="card">
  <p>{{ __('goals.intro') }}</p>
  @if(session('status')) <p>{{ session('status') }}</p> @endif
  @livewire('goal-tracker')
  </div>
@endsection
