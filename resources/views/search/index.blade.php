@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('search.title') }}</h1>
  @livewire('book-search', ['initialQuery' => $query])
@endsection
