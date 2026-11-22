@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.library_title') }}</h1>
  @if($userBooks->isEmpty())
    <p>{{ __('books.empty_library') }}</p>
    <p><a class="btn" href="{{ route('books.index', ['locale' => app()->getLocale()]) }}">{{ __('books.browse_title') }}</a></p>
  @else
    <div class="book-grid">
      @foreach($userBooks as $userBook)
        <div>
          <x-book-card :book="$userBook->book" />
          @if(in_array($userBook->book->id, $finishedBookIds ?? [], true))
            <span class="badge">{{ __('reader.finished_badge') }}</span>
          @endif
        </div>
      @endforeach
    </div>
    <div style="margin-top:16px">{{ $userBooks->links() }}</div>
  @endif
@endsection
