@extends('layouts.app')

@section('content')
<h1 class="serif">{{ __('reader.audio_title') }} — {{ $book->title }}</h1>

@if($hasAudioFile)
  <audio id="audiobook-player" src="{{ $audioUrl }}" controls preload="metadata" style="width:100%;"></audio>
  @livewire('audio-player', ['book' => $book])
@else
  <div class="alert">
    <strong>{{ __('reader.audio_coming_soon') }}</strong>
    <p>{{ __('reader.audio_coming_soon_text') }}</p>
  </div>
  <p><a href="{{ route('tts.show', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}" class="btn">{{ __('reader.listen_tts') }}</a></p>
@endif

<p><a href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}" class="btn">{{ __('reader.back_to_book') }}</a></p>
@endsection
