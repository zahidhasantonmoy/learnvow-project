<div>
  <div id="reader-vocabulary-add">
    <form wire:submit.prevent="saveVocabulary">
      <label>{{ __('reader.vocabulary_word') }}
        <input type="text" wire:model="vocabWord" maxlength="100">
      </label>
      <label>{{ __('reader.vocabulary_meaning_en') }}
        <input type="text" wire:model="vocabMeaning" maxlength="2000">
      </label>
      <button type="submit" class="btn">{{ __('reader.vocabulary_add_to') }}</button>
    </form>
  </div>
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
