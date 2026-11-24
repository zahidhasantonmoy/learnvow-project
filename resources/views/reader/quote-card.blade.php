@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('reader.quote_card_title') }}</h1>
  <p>{{ __('reader.quote_card_intro') }}</p>
  @if($highlight === null)
    <p>{{ __('reader.quote_card_empty') }}</p>
    <p><a class="btn" href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}">{{ __('reader.quote_card_go_to_reader') }}</a></p>
  @else
    @livewire('quote-card', ['book' => $book, 'highlight' => $highlight])
  @endif
@endsection
