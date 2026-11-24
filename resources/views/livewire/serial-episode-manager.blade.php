<div>
  <form wire:submit.prevent="addEpisode">
    <label>{{ __('books.serial_episode_title') }}
      <input type="text" wire:model="title" maxlength="255" required>
    </label>
    @error('title') <p>{{ $message }}</p> @enderror
    <label>{{ __('books.serial_episode_content') }}
      <textarea wire:model="content" required></textarea>
    </label>
    <label><input type="checkbox" wire:model="publishNow"> {{ __('books.serial_publish_now') }}</label>
    <label><input type="checkbox" wire:model="isFree"> {{ __('books.serial_episode_free') }}</label>
    <button type="submit" class="btn">{{ __('books.serial_episode_add') }}</button>
  </form>
  <ul>
    @foreach($episodes as $episode)
      <li>
        {{ $episode->episode_no }}. {{ $episode->title }}
        @if($episode->published_at)
          <span class="badge">{{ __('books.serial_published') }}</span>
        @else
          <span class="badge">{{ __('books.serial_draft') }}</span>
          <button type="button" wire:click="publishEpisode({{ $episode->id }})">{{ __('books.serial_publish') }}</button>
        @endif
      </li>
    @endforeach
  </ul>
</div>
