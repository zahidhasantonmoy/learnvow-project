@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ $serial->title }}</h1>
  @if($serial->description) <p>{{ $serial->description }}</p> @endif
  <p>
    <span class="badge">{{ __('books.serial_status_'.$serial->status) }}</span>
    @if($serial->approved_at === null)
      <span class="badge">{{ __('author.serial_awaiting_approval') }}</span>
    @endif
    <a href="{{ route('serials.show', ['locale' => app()->getLocale(), 'serial' => $serial->id]) }}">{{ __('author.serial_view_public') }}</a>
  </p>
  <h2>{{ __('author.serial_episodes_heading') }}</h2>
  @livewire('serial-episode-manager', ['serial' => $serial])
@endsection
