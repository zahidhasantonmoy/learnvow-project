<div>
  <h2>{{ $ticket->ticket_no }} — {{ $ticket->subject }}</h2>
  <p>{{ __('support.tickets.status') }}: {{ __('support.tickets.status_'.$ticket->status) }}</p>

  @if($messages->isEmpty())
    <p>{{ __('support.tickets.empty') }}</p>
  @else
    @foreach($messages as $message)
      <div class="ticket-message">
        <strong>{{ $message->user?->name ?? '—' }}</strong>
        <span>{{ $message->created_at?->format('Y-m-d H:i') }}</span>
        @if($message->is_internal)
          <em>{{ __('support.tickets.internal_note') }}</em>
        @endif
        <p>{{ $message->body }}</p>
      </div>
    @endforeach
  @endif

  <form wire:submit="reply">
    <div class="form-field">
      <label for="thread-body">{{ __('support.tickets.message') }}</label>
      <textarea id="thread-body" wire:model="body" rows="3" placeholder="{{ __('support.tickets.reply_placeholder') }}"></textarea>
      @error('body') <span class="error">{{ $message }}</span> @enderror
    </div>
    @if($isStaff)
      <div class="form-field">
        <label>
          <input type="checkbox" wire:model="isInternal">
          {{ __('support.tickets.internal_note') }}
        </label>
      </div>
      <div class="form-field">
        <label for="thread-status">{{ __('support.tickets.status') }}</label>
        <select id="thread-status" wire:model="status" wire:change="changeStatus($event.target.value)">
          @foreach(['open', 'in_progress', 'waiting_reply', 'resolved', 'closed'] as $status)
            <option value="{{ $status }}">{{ __('support.tickets.status_'.$status) }}</option>
          @endforeach
        </select>
      </div>
      <button type="button" class="btn" wire:click="escalate('admin')">{{ __('support.tickets.escalate') }} — {{ __('support.tickets.escalated_to_admin') }}</button>
      <button type="button" class="btn" wire:click="escalate('super_admin')">{{ __('support.tickets.escalate') }} — {{ __('support.tickets.escalated_to_super_admin') }}</button>
    @endif
    <button type="submit" class="btn">{{ __('support.tickets.reply') }}</button>
  </form>
</div>
