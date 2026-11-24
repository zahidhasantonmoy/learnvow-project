<div>
  <input type="search" wire:model.live="search" placeholder="{{ __('reader.vocabulary_search') }}">
  <form wire:submit.prevent="addWord">
    <label>{{ __('reader.vocabulary_word') }}
      <input type="text" wire:model="word" maxlength="100" required>
    </label>
    <label>{{ __('reader.vocabulary_meaning_en') }}
      <input type="text" wire:model="meaningEn" maxlength="2000">
    </label>
    <label>{{ __('reader.vocabulary_meaning_bn') }}
      <input type="text" wire:model="meaningBn" maxlength="2000">
    </label>
    <label>{{ __('reader.vocabulary_example') }}
      <input type="text" wire:model="example" maxlength="2000">
    </label>
    <button type="submit" class="btn">{{ __('reader.vocabulary_add') }}</button>
  </form>
  <ul>
    @foreach($words as $word)
      <li>
        <strong>{{ $word->word }}</strong>
        @if($word->meaning_en) — {{ $word->meaning_en }} @endif
        @if($word->meaning_bn) ({{ $word->meaning_bn }}) @endif
        @if($word->example) <em>{{ $word->example }}</em> @endif
        @if($word->book) <span>{{ $word->book->title }}</span> @endif
        <button type="button" wire:click="deleteWord({{ $word->id }})">{{ __('reader.vocabulary_delete') }}</button>
      </li>
    @endforeach
  </ul>
  {{ $words->links() }}
</div>
