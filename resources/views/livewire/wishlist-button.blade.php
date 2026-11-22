<div>
  <button type="button" wire:click="toggle" class="btn {{ $inWishlist ? '' : 'btn-secondary' }}">
    {{ $inWishlist ? __('books.in_wishlist') : __('books.add_to_wishlist') }}
  </button>
</div>
