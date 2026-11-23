@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.promos_title') }}</h1>
  <p><a href="{{ route('author.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('author.back_to_studio') }}</a></p>
  <p>{{ __('author.promo_share_note') }}</p>

  @if(! $canCreate)
    <p class="alert">{{ __('author.promo_no_limits') }}</p>
  @else
    <form method="POST" action="{{ route('author.promos.store', ['locale' => app()->getLocale()]) }}">
      @csrf
      <p>
        <label for="book_id">{{ __('author.promo_book') }} *</label><br>
        <select id="book_id" name="book_id" required>
          @foreach($books as $book)
            <option value="{{ $book->id }}">{{ $book->title }}</option>
          @endforeach
        </select>
      </p>
      <p>
        <label for="code">{{ __('author.promo_code') }} ({{ __('author.optional') }})</label><br>
        <input type="text" id="code" name="code" maxlength="32">
      </p>
      <p>
        <label for="percent">{{ __('author.promo_percent') }} * (1–{{ $limits['max_percent'] }})</label><br>
        <input type="number" id="percent" name="percent" min="1" max="{{ $limits['max_percent'] }}" required>
      </p>
      <p>
        <label for="duration_days">{{ __('author.promo_duration_days') }} * (1–{{ $limits['max_duration_days'] }})</label><br>
        <input type="number" id="duration_days" name="duration_days" min="1" max="{{ $limits['max_duration_days'] }}" required>
      </p>
      <p>
        <label for="max_uses">{{ __('author.promo_max_uses') }} ({{ __('author.optional') }})</label><br>
        <input type="number" id="max_uses" name="max_uses" min="1">
      </p>
      <p><button type="submit" class="btn">{{ __('author.promo_create') }}</button></p>
    </form>
  @endif

  @if($promos->isEmpty())
    <p>{{ __('author.promos_empty') }}</p>
  @else
    <ul>
      @foreach($promos as $promo)
        <li>
          <strong>{{ $promo->code }}</strong> — {{ $promo->percent }}% —
          {{ $books->firstWhere('id', $promo->book_id)?->title ?? '' }} —
          {{ $promo->starts_at }} → {{ $promo->ends_at }}
        </li>
      @endforeach
    </ul>
  @endif
@endsection
