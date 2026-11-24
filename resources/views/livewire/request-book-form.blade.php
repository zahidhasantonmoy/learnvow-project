<div>
  <p>{{ __('books.request_remaining', ['count' => $remaining]) }}</p>
  <form wire:submit.prevent="submit">
    <label>{{ __('books.request_title') }}
      <input type="text" wire:model="title" maxlength="255" required>
    </label>
    @error('title') <p>{{ $message }}</p> @enderror
    <label>{{ __('books.request_author') }}
      <input type="text" wire:model="authorName" maxlength="255">
    </label>
    <label>{{ __('books.request_reason') }}
      <textarea wire:model="reason" maxlength="1000"></textarea>
    </label>
    <button type="submit" class="btn">{{ __('books.request_submit') }}</button>
  </form>
  @if($sameTitleCount !== null)
    <p>{{ __('books.request_same_title', ['count' => $sameTitleCount]) }}</p>
  @endif
</div>
