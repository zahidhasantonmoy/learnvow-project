<div>
  <p>
    {{ __('author.unread') }}: {{ $unreadCount }}
    @if($unreadCount > 0)
      <button type="button" class="btn btn-secondary" wire:click="markAllRead">{{ __('author.mark_all_read') }}</button>
    @endif
  </p>
  @if($notifications->isEmpty())
    <p>{{ __('author.notifications_empty') }}</p>
  @else
    <ul>
      @foreach($notifications as $notification)
        @php $described = \App\Notifications\AuthorNotification::describe($notification->data); @endphp
        <li>
          <strong>{{ $described['text'] }}</strong>
          @if($described['url'] !== null)
            <a href="{{ $described['url'] }}">{{ __('author.notifications.view') }}</a>
          @endif
          @if($notification->read_at === null) <em>({{ __('author.unread') }})</em>@endif
          <br>
          <small>{{ $notification->created_at }}</small>
          @if($notification->read_at === null)
            <button type="button" wire:click="markRead('{{ $notification->id }}')">{{ __('author.mark_read') }}</button>
          @endif
        </li>
      @endforeach
    </ul>
  @endif
</div>
