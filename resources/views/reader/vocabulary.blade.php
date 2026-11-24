@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('reader.vocabulary_title') }}</h1>
  @if(session('status')) <p>{{ session('status') }}</p> @endif
  @livewire('vocabulary-manager', ['search' => request('q', '')])
@endsection
