<div>
  @if($preorder)
    <p>{{ __('books.preorder_reserved') }}</p>
    <button type="button" wire:click="cancel" class="btn">{{ __('books.preorder_cancel') }}</button>
  @else
    <button type="button" wire:click="reserve" class="btn">{{ __('books.preorder_reserve') }}</button>
  @endif
  @error('preorder') <p>{{ $message }}</p> @enderror
</div>
