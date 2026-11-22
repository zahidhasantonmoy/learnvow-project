@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.sample_title', ['title' => $book->title]) }}</h1>
  <p>{{ $book->author->name }} · {{ __('books.sample_percent', ['percent' => $book->sample_percent]) }}</p>
  <p>{{ $book->language === 'bn' ? $book->description : ($book->description_en ?? $book->description) }}</p>
  @guest
    <div class="alert">
      <p>{{ __('books.sample_login_wall') }}</p>
      @if(\Illuminate\Support\Facades\Route::has('login'))
        <a class="btn" href="{{ route('login', ['locale' => app()->getLocale()]) }}">{{ __('nav.login') }}</a>
      @endif
    </div>
  @endguest
  @auth
    @if($canRead)
      <p>{{ __('books.sample_full_access') }}</p>
    @else
      <p>{{ __('books.sample_limited') }}</p>
    @endif
  @endauth
  <p><a href="{{ route('books.show', ['locale' => app()->getLocale(), 'slug' => $book->slug]) }}">&larr; {{ $book->title }}</a></p>
@endsection
