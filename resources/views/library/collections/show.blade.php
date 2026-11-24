@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ $collection->name }}</h1>
  @if($collection->description) <p>{{ $collection->description }}</p> @endif
  @if($collection->items->isEmpty())
    <p>{{ __('books.collections_empty') }}</p>
  @else
    <div class="book-grid">
      @foreach($collection->items as $item)
        <div>
          <x-book-card :book="$item->book" />
          <form method="POST" action="{{ route('collections.books.remove', ['locale' => app()->getLocale(), 'collection' => $collection->id, 'book' => $item->book->slug]) }}">
            @csrf
            @method('DELETE')
            <button type="submit">{{ __('books.remove') }}</button>
          </form>
        </div>
      @endforeach
    </div>
  @endif
  <p><a href="{{ route('collections.index', ['locale' => app()->getLocale()]) }}">{{ __('books.collections_back') }}</a></p>
@endsection
