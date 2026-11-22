<div>
  <h2>{{ __('reader.bookmarks') }}</h2>
  @if($bookmarks->isEmpty())
    <p>{{ __('reader.no_bookmarks') }}</p>
  @else
    <ul>
      @foreach($bookmarks as $bookmark)
        <li>
          <a href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $bookmark->book->slug]) }}">{{ $bookmark->label ?? $bookmark->book->title }}</a>
        </li>
      @endforeach
    </ul>
  @endif
</div>
