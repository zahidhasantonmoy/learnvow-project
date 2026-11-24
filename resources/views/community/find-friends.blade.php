@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('community.find_friends_heading') }}</h1>

  <form method="GET" action="{{ route('community.find-friends', ['locale' => app()->getLocale()]) }}">
    <p>
      <input type="text" name="q" value="{{ $query }}" placeholder="{{ __('community.search_placeholder') }}">
      <button type="submit" class="btn">{{ __('community.search') }}</button>
    </p>
  </form>

  @if($query !== '')
    @if($users->isEmpty())
      <p>{{ __('community.no_results') }}</p>
    @else
      <ul>
        @foreach($users as $found)
          <li>{{ $found->name }}</li>
        @endforeach
      </ul>
    @endif
  @else
    {{-- QA minor: with no search the page was an empty state with no
         guidance — say what to do. --}}
    <p>{{ __('community.find_friends_hint') }}</p>
  @endif
@endsection
