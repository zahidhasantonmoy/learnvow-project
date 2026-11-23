@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.publish_heading') }}</h1>
  @if(! empty($pendingApproval))
    {{-- Minor: a pending author sees the friendly waiting message, not a
         bare 403 and not the publish form. --}}
    <div class="alert">{{ __('author.apply_waiting_approval') }}</div>
    <p><a href="{{ route('author.apply', ['locale' => app()->getLocale()]) }}">{{ __('author.apply_title') }}</a></p>
  @else
    <p><a href="{{ route('author.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('author.back_to_studio') }}</a></p>
    @livewire('author-upload')
  @endif
@endsection
