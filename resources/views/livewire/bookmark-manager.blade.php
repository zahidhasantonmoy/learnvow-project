<div>
  <h2>{{ __('reader.bookmarks') }}</h2>
  @if($bookmarks->isEmpty())
    <p>{{ __('reader.no_bookmarks') }}</p>
  @else
    <ul>
      @foreach($bookmarks as $bookmark)
        <li>
          <a href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $bookmark->book->slug]) }}">{{ $bookmark->book->title }} — {{ $bookmark->label ?? $bookmark->cfi }}</a>
          <button type="button" wire:click="deleteBookmark({{ $bookmark->id }})" wire:confirm="{{ __('reader.delete') }}?" class="btn">{{ __('reader.delete') }}</button>
        </li>
      @endforeach
    </ul>
  @endif

  <h2>{{ __('reader.highlights') }}</h2>
  @if($highlights->isEmpty())
    <p>{{ __('reader.no_highlights') }}</p>
  @else
    <ul>
      @foreach($highlights as $highlight)
        <li>
          <a href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $highlight->book->slug]) }}">{{ $highlight->book->title }} — {{ $highlight->color }}</a>
          @if($highlight->note)<p>{{ $highlight->note }}</p>@endif
          <button type="button" wire:click="deleteHighlight({{ $highlight->id }})" class="btn">{{ __('reader.delete') }}</button>
        </li>
      @endforeach
    </ul>
  @endif

  <h2>{{ __('reader.notes') }}</h2>
  @if($notes->isEmpty())
    <p>{{ __('reader.no_notes') }}</p>
  @else
    <ul>
      @foreach($notes as $note)
        <li>
          <a href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $note->book->slug]) }}">{{ $note->book->title }}</a>
          <p>{{ $note->body }}</p>
          <button type="button" wire:click="deleteNote({{ $note->id }})" class="btn">{{ __('reader.delete') }}</button>
        </li>
      @endforeach
    </ul>
  @endif
</div>
