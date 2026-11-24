@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('community.clubs_heading') }}</h1>

  <form method="POST" action="{{ route('clubs.store', ['locale' => app()->getLocale()]) }}">
    @csrf
    <p>
      <label for="name">{{ __('community.club_name') }} *</label><br>
      <input type="text" id="name" name="name" maxlength="255" required>
    </p>
    <p>
      <label for="description">{{ __('community.club_description') }}</label><br>
      <textarea id="description" name="description"></textarea>
    </p>
    <p>
      <label><input type="checkbox" name="is_public" value="1" checked> {{ __('community.club_public') }}</label>
    </p>
    <p>
      <label for="reading_goal">{{ __('community.club_reading_goal') }}</label><br>
      <input type="text" id="reading_goal" name="reading_goal" maxlength="255">
    </p>
    <p><button type="submit" class="btn">{{ __('community.create_club') }}</button></p>
  </form>

  @if($clubs->isEmpty())
    <p>{{ __('community.clubs_empty') }}</p>
  @else
    <ul>
      @foreach($clubs as $club)
        <li>
          <a href="{{ route('clubs.show', ['locale' => app()->getLocale(), 'club' => $club->id]) }}">{{ $club->name }}</a>
          — {{ __('community.member_count', ['count' => $club->members_count ?? $club->members()->count()]) }}
        </li>
      @endforeach
    </ul>
  @endif
@endsection
