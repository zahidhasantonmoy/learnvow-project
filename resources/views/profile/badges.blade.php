@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.badges_title') }}</h1>
  @if(session('status')) <p>{{ session('status') }}</p> @endif
  <div class="card">
  <ul class="badge-list">
    @foreach($badges as $badge)
      {{-- UI-15: earned vs unearned used to differ only by a small pill.
           Earned badges get a green card + check; unearned are muted
           and greyed so the state is visible at a glance. --}}
      <li class="{{ in_array($badge->id, $earnedIds, true) ? 'badge-item badge-item-earned' : 'badge-item badge-item-locked' }}">
        <strong>{{ in_array($badge->id, $earnedIds, true) ? '✓ ' : '' }}{{ app()->getLocale() === 'bn' ? $badge->name : ($badge->name_en ?? $badge->name) }}</strong>
        @if(in_array($badge->id, $earnedIds, true))
          <span class="badge">{{ __('books.badge_earned') }}</span>
        @else
          <span>{{ app()->getLocale() === 'bn' ? $badge->description : ($badge->description_en ?? $badge->description) }}</span>
        @endif
      </li>
    @endforeach
  </ul>
  </div>
@endsection
