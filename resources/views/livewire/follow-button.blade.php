<div>
  <button type="button" wire:click="toggle" class="btn {{ $isFollowing ? 'btn-secondary' : '' }}">
    {{ $isFollowing ? __('books.following') : __('books.follow') }}
  </button>
  <span>{{ __('books.followers_count', ['count' => $followersCount]) }}</span>
</div>
