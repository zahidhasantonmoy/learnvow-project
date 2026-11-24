@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ $club->name }}</h1>
  @if($club->description)
    <p>{{ $club->description }}</p>
  @endif
  @if($club->reading_goal)
    <p>{{ $club->reading_goal }}</p>
  @endif
  <p>{{ __('community.member_count', ['count' => $club->members->count()]) }}</p>

  @if(! $club->hasMember(auth()->user()))
    <form method="POST" action="{{ route('clubs.join', ['locale' => app()->getLocale(), 'club' => $club->id]) }}">
      @csrf
      <p><button type="submit" class="btn">{{ __('community.join') }}</button></p>
    </form>
  @else
    @livewire('club-discussion', ['club' => $club])
  @endif
@endsection
