<div>
  <form wire:submit.prevent="createCollection">
    <label>{{ __('books.collections_name') }}
      <input type="text" wire:model="name" maxlength="100" required>
    </label>
    @error('name') <p>{{ $message }}</p> @enderror
    <label>{{ __('books.collections_description') }}
      <input type="text" wire:model="description" maxlength="500">
    </label>
    <label><input type="checkbox" wire:model="isPublic"> {{ __('books.collections_public') }}</label>
    <button type="submit" class="btn">{{ __('books.collections_create') }}</button>
  </form>

  <ul>
    @foreach($collections as $collection)
      <li>
        <button type="button" wire:click="selectCollection({{ $collection->id }})">{{ $collection->name }} ({{ $collection->items_count }})</button>
        @if($editingId === $collection->id)
          <input type="text" wire:model="editName" maxlength="100">
          <button type="button" wire:click="updateCollection">{{ __('books.save') }}</button>
        @else
          <button type="button" wire:click="startEdit({{ $collection->id }})">{{ __('books.edit') }}</button>
        @endif
        <button type="button" wire:click="deleteCollection({{ $collection->id }})" onclick="return confirm('{{ __('books.collections_delete_confirm') }}')">{{ __('books.delete') }}</button>
      </li>
    @endforeach
  </ul>

  @if($selected)
    <h2>{{ $selected->name }}</h2>
    <ul>
      @foreach($selected->items as $item)
        <li>
          {{ $item->book->title }}
          <button type="button" wire:click="moveItem({{ $item->id }}, 'up')">↑</button>
          <button type="button" wire:click="moveItem({{ $item->id }}, 'down')">↓</button>
          <button type="button" wire:click="removeBook({{ $item->book_id }})">{{ __('books.remove') }}</button>
        </li>
      @endforeach
    </ul>
    @if($selected->items->isEmpty())
      <p>{{ __('books.collections_empty') }}</p>
    @endif
  @endif
</div>
