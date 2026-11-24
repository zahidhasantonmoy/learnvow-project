<div>
  <form wire:submit.prevent="save">
    <fieldset>
      <legend>{{ __('books.taste_genres') }}</legend>
      @foreach($categories as $category)
        <label>
          <input type="checkbox" value="{{ $category->id }}" wire:model="genres">
          {{ app()->getLocale() === 'bn' ? $category->name : ($category->name_en ?? $category->name) }}
        </label>
      @endforeach
    </fieldset>
    <fieldset>
      <legend>{{ __('books.taste_authors') }}</legend>
      <input type="search" wire:model.live="authorSearch" placeholder="{{ __('books.taste_author_search') }}">
      @foreach($foundAuthors as $author)
        <button type="button" wire:click="toggleAuthor({{ $author->id }})">{{ $author->name }}</button>
      @endforeach
      <ul>
        @foreach($chosenAuthors as $author)
          <li>{{ $author->name }} <button type="button" wire:click="toggleAuthor({{ $author->id }})">{{ __('books.remove') }}</button></li>
        @endforeach
      </ul>
    </fieldset>
    <fieldset>
      <legend>{{ __('books.taste_languages') }}</legend>
      <label><input type="checkbox" value="bn" wire:model="languages"> {{ __('books.language_bn') }}</label>
      <label><input type="checkbox" value="en" wire:model="languages"> {{ __('books.language_en') }}</label>
    </fieldset>
    <label>{{ __('books.taste_reading_time') }}
      <select wire:model="readingTime">
        <option value="">{{ __('books.taste_reading_time_any') }}</option>
        <option value="morning">{{ __('books.reading_time_morning') }}</option>
        <option value="afternoon">{{ __('books.reading_time_afternoon') }}</option>
        <option value="evening">{{ __('books.reading_time_evening') }}</option>
        <option value="night">{{ __('books.reading_time_night') }}</option>
      </select>
    </label>
    <button type="submit" class="btn">{{ __('books.taste_save') }}</button>
  </form>
</div>
