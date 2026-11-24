@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.taste_title') }}</h1>
  <p>{{ __('books.taste_intro') }}</p>
  @if(session('status')) <p>{{ session('status') }}</p> @endif
  @livewire('taste-profile-form')
@endsection
