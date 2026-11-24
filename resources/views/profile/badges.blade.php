@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.badges_title') }}</h1>
  @if(session('status')) <p>{{ session('status') }}</p> @endif
  <ul>
    @foreach($badges as $badge)
      <li>
        <strong>{{ app()->getLocale() === 'bn' ? $badge->name : ($badge->name_en ?? $badge->name) }}</strong>
        @if(in_array($badge->id, $earnedIds, true))
          <span class="badge">{{ __('books.badge_earned') }}</span>
        @else
          <span>{{ app()->getLocale() === 'bn' ? $badge->description : ($badge->description_en ?? $badge->description) }}</span>
        @endif
      </li>
    @endforeach
  </ul>
@endsection
