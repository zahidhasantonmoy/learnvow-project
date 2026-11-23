<div>
  @if($createdTicketNo)
    <p class="alert">{{ __('support.tickets.opened') }} ({{ $createdTicketNo }})</p>
  @endif
  <form wire:submit="submit">
    <div class="form-field">
      <label for="ticket-subject">{{ __('support.tickets.subject') }}</label>
      <input id="ticket-subject" type="text" wire:model="subject">
      @error('subject') <span class="error">{{ $message }}</span> @enderror
    </div>
    <div class="form-field">
      <label for="ticket-category">{{ __('support.tickets.category') }}</label>
      <select id="ticket-category" wire:model="category">
        @foreach(['payment', 'book', 'technical', 'account', 'other'] as $category)
          <option value="{{ $category }}">{{ __('support.tickets.category_'.$category) }}</option>
        @endforeach
      </select>
      @error('category') <span class="error">{{ $message }}</span> @enderror
    </div>
    <div class="form-field">
      <label for="ticket-body">{{ __('support.tickets.message') }}</label>
      <textarea id="ticket-body" wire:model="body" rows="4"></textarea>
      @error('body') <span class="error">{{ $message }}</span> @enderror
    </div>
    <button type="submit" class="btn">{{ __('support.tickets.open_ticket') }}</button>
  </form>
</div>
