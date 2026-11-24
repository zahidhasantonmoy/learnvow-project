@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.my_serials') }}</h1>
  <p><a class="btn" href="{{ route('author.serials.create', ['locale' => app()->getLocale()]) }}">{{ __('author.serial_create') }}</a></p>
  @if($serials->isEmpty())
    <p>{{ __('author.serials_empty') }}</p>
  @else
    <ul>
      @foreach($serials as $serial)
        <li>
          <a href="{{ route('author.serials.show', ['locale' => app()->getLocale(), 'serial' => $serial->id]) }}">{{ $serial->title }}</a>
          — {{ $serial->episodes_count }} {{ __('books.serial_episodes') }}
          @if($serial->approved_at === null)
            <span class="badge">{{ __('author.serial_awaiting_approval') }}</span>
          @endif
        </li>
      @endforeach
    </ul>
  @endif
@endsection
