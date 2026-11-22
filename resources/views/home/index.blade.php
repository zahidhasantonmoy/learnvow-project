@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.home_heading') }}</h1>
  <p>{{ __('books.home_intro') }}</p>

  <h2>{{ __('books.trending') }}</h2>
  <div class="book-grid">
    @foreach($trending as $book)
      <x-book-card :book="$book" />
    @endforeach
  </div>

  <h2>{{ __('books.newest') }}</h2>
  <div class="book-grid">
    @foreach($newest as $book)
      <x-book-card :book="$book" />
    @endforeach
  </div>

  <h2>{{ __('books.free_books') }}</h2>
  <div class="book-grid">
    @foreach($freeBooks as $book)
      <x-book-card :book="$book" />
    @endforeach
  </div>

  <h2>{{ __('books.categories') }}</h2>
  <ul>
    @foreach($categories as $category)
      <li><a href="{{ route('categories.show', ['locale' => app()->getLocale(), 'slug' => $category->slug]) }}">{{ $category->displayName() }}</a></li>
    @endforeach
  </ul>
@endsection
