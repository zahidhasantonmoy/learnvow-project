@extends('layouts.app')

@section('content')
<h1 class="serif">{{ __('reader.settings_heading') }}</h1>
<p>{{ $book->title }}</p>
<p>{{ __('reader.settings_note') }}</p>

@livewire('reading-settings', ['book' => $book])

<p><a href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}" class="btn">{{ __('reader.back_to_book') }}</a></p>
@endsection
