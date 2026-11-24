@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('print.order_heading') }} — {{ $book->title }}</h1>
  <p><a href="{{ route('books.show', ['locale' => app()->getLocale(), 'slug' => $book->slug]) }}">{{ $book->title }}</a></p>

  @livewire('print-order-form', ['book' => $book])
@endsection
