@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.request_book_title') }}</h1>
  <p>{{ __('books.request_intro') }}</p>
  @if(session('status')) <p>{{ session('status') }}</p> @endif
  @livewire('request-book-form')
  @if($topRequested->isNotEmpty())
    <h2>{{ __('books.request_top') }}</h2>
    <ul>
      @foreach($topRequested as $row)
        <li>{{ $row->title }} — {{ __('books.request_same_title', ['count' => $row->requests_count]) }}</li>
      @endforeach
    </ul>
  @endif
@endsection
