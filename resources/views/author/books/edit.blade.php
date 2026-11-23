@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.edit_title') }} — {{ $book->title }}</h1>
  <p><a href="{{ route('author.books', ['locale' => app()->getLocale()]) }}">{{ __('author.back_to_studio') }}</a></p>
  @livewire('author-book-editor', ['book' => $book])
@endsection
