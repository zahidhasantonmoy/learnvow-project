@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.books_title') }}</h1>
  <p>
    <a href="{{ route('author.publish', ['locale' => app()->getLocale()]) }}" class="btn">{{ __('author.publish_book') }}</a>
    <a href="{{ route('author.dashboard', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('author.back_to_studio') }}</a>
  </p>

  @if($books->isEmpty())
    <p>{{ __('author.books_empty') }}</p>
  @else
    <ul>
      @foreach($books as $book)
        <li>
          <strong>{{ $book->title }}</strong>
          @if($book->is_paused)
            — {{ __('author.book_status_paused') }}
          @elseif($book->status === 'published')
            — {{ __('author.book_status_live') }}
          @elseif($book->status === 'in_review')
            — {{ __('author.book_status_in_review') }}
          @elseif($book->status === 'rejected')
            — {{ __('author.book_status_returned') }}
          @elseif($book->status === 'draft')
            — {{ __('author.book_status_draft') }}
          @else
            — {{ $book->status }}
          @endif
          — ৳{{ number_format($book->price_bdt) }}
          @if($book->author_id === $author->id) <em>({{ __('author.own_book') }})</em>@endif
          <br>
          <a href="{{ route('author.books.edit', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}">{{ __('author.edit') }}</a> ·
          <a href="{{ route('author.books.audio', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}">{{ __('author.add_audiobook') }}</a> ·
          <a href="{{ route('author.print-settings.edit', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}">{{ __('author.print_settings') }}</a> ·
          <a href="{{ route('author.reviews', ['locale' => app()->getLocale()]) }}">{{ __('author.reviews') }}</a>
          @if($book->is_paused)
            <form method="POST" action="{{ route('author.books.resume', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}" style="display:inline">
              @csrf
              <button type="submit" class="btn btn-secondary">{{ __('author.resume') }}</button>
            </form>
          @else
            <form method="POST" action="{{ route('author.books.pause', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}" style="display:inline">
              @csrf
              <button type="submit" class="btn btn-secondary">{{ __('author.pause') }}</button>
            </form>
          @endif
          @if($book->status === 'rejected')
            <form method="POST" action="{{ route('author.books.appeal', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}" style="display:inline">
              @csrf
              <input type="text" name="note" placeholder="{{ __('author.appeal_note') }}" required>
              <button type="submit" class="btn">{{ __('author.appeal') }}</button>
            </form>
          @endif
        </li>
      @endforeach
    </ul>
    {{ $books->links() }}
  @endif
@endsection
