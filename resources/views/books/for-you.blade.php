@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.for_you_title') }}</h1>
  <p>{{ __('books.for_you_intro') }}</p>
  @if(empty($recommendations))
    <p>{{ __('books.for_you_empty') }}</p>
  @else
    <div class="book-grid">
      @foreach($recommendations as $item)
        <div>
          <x-book-card :book="$item['book']" />
          <p>{{ $item['reason'] }}</p>
        </div>
      @endforeach
    </div>
  @endif
@endsection
