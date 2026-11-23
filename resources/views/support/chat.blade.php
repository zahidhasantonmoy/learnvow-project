@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.support_heading') }}</h1>

  @isset($ticket)
    @if(class_exists(\App\Livewire\TicketThread::class))
      @livewire('ticket-thread', ['ticket' => $ticket])
    @else
      <p>{{ __('author.support_thread_unavailable') }}</p>
    @endif
  @else
    @if(isset($tickets) && $tickets->count() > 0)
      <ul>
        @foreach($tickets as $t)
          <li>
            <a href="{{ route('support.chat.show', ['locale' => app()->getLocale(), 'ticket' => $t->id]) }}">{{ $t->ticket_no ?? $t->id }} — {{ $t->subject }}</a>
            ({{ $t->status }})
          </li>
        @endforeach
      </ul>
      {{ $tickets->links() }}
    @else
      <p>{{ __('author.support_empty') }}</p>
    @endif

    @if(class_exists(\App\Livewire\SupportTicketForm::class))
      @livewire('support-ticket-form')
    @endif
  @endisset
@endsection
