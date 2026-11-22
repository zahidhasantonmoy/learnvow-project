@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ $author->name }}</h1>
  @if($author->pen_name)
    <p>{{ __('books.pen_name', ['name' => $author->pen_name]) }}</p>
  @endif
  <p>{{ app()->getLocale() === 'bn' ? ($author->bio ?? $author->bio_en) : ($author->bio_en ?? $author->bio) }}</p>
  @livewire('follow-button', ['author' => $author, 'isFollowing' => $isFollowing, 'followersCount' => $followersCount])
  <h2>{{ __('books.books_by_author', ['name' => $author->name]) }}</h2>
  @if($books->isEmpty())
    <p>{{ __('books.no_books') }}</p>
  @else
    <div class="book-grid">
      @foreach($books as $book)
        <x-book-card :book="$book" />
      @endforeach
    </div>
  @endif
@endsection
