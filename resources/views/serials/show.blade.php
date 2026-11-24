@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ $serial->title }}</h1>
  @if($serial->description) <p>{{ $serial->description }}</p> @endif
  <p>
    <span class="badge">{{ __('books.serial_status_'.$serial->status) }}</span>
    @if($serial->is_subscription_included) <span class="badge">{{ __('books.serial_premium_included') }}</span> @endif
  </p>
  <form method="POST" action="{{ route('serials.subscribe', ['locale' => app()->getLocale(), 'serial' => $serial->id]) }}">
    @csrf
    <button type="submit" class="btn">{{ $subscribed ? __('books.serial_unsubscribe') : __('books.serial_subscribe') }}</button>
  </form>
  <h2>{{ __('books.serial_episodes') }}</h2>
  @if($serial->episodes->isEmpty())
    <p>{{ __('books.serial_no_episodes') }}</p>
  @else
    <ul>
      @foreach($serial->episodes as $episode)
        <li>
          @if($episode->isPublished())
            <a href="{{ route('serials.episodes.show', ['locale' => app()->getLocale(), 'serial' => $serial->id, 'episode' => $episode->id]) }}">{{ $episode->episode_no }}. {{ $episode->title }}</a>
          @else
            {{ $episode->episode_no }}. {{ $episode->title }} <span class="badge">{{ __('books.serial_coming_soon') }}</span>
          @endif
        </li>
      @endforeach
    </ul>
  @endif
@endsection
