@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.collections_title') }}</h1>
  <div class="card">
  <p>{{ __('books.collections_intro') }}</p>
  @if(session('status')) <p>{{ session('status') }}</p> @endif
  @livewire('collection-manager')
  </div>
@endsection
