@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.library_title') }}</h1>
  @if($userBooks->isEmpty())
    <p>{{ __('books.empty_library') }}</p>
    <p><a class="btn" href="{{ route('books.index', ['locale' => app()->getLocale()]) }}">{{ __('books.browse_title') }}</a></p>
  @else
    <div class="book-grid">
      @foreach($userBooks as $userBook)
        <x-book-card :book="$userBook->book" />
      @endforeach
    </div>
    <div style="margin-top:16px">{{ $userBooks->links() }}</div>
  @endif
@endsection
