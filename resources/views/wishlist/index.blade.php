@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.wishlist_title') }}</h1>
  @if($items->isEmpty())
    <p>{{ __('books.empty_wishlist') }}</p>
    <p><a class="btn" href="{{ route('books.index', ['locale' => app()->getLocale()]) }}">{{ __('books.browse_title') }}</a></p>
  @else
    <div class="book-grid">
      @foreach($items as $item)
        <x-book-card :book="$item->book" />
      @endforeach
    </div>
    <div style="margin-top:16px">{{ $items->links() }}</div>
  @endif
@endsection
