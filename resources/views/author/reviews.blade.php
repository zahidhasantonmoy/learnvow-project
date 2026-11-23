@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.reviews_title') }}</h1>
  <p><a href="{{ route('author.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('author.back_to_studio') }}</a></p>
  <p>{{ __('author.cannot_delete_review') }}</p>

  @if($reviews->isEmpty())
    <p>{{ __('author.reviews_empty') }}</p>
  @else
    <ul>
      @foreach($reviews as $review)
        <li>
          <strong>{{ $review->book->title }}</strong> — {{ $review->rating }}/5<br>
          {{ $review->body }}<br>
          <small>{{ $review->user->name ?? '' }} · {{ $review->created_at }}</small>
          @if(isset($replies[$review->id]))
            <p><strong>{{ __('author.reply_existing') }}:</strong> {{ $replies[$review->id]->body }}</p>
          @endif
          <form method="POST" action="{{ route('author.reviews.reply', ['locale' => app()->getLocale(), 'review' => $review->id]) }}">
            @csrf
            <input type="text" name="body" placeholder="{{ __('author.reply_placeholder') }}" value="{{ $replies[$review->id]->body ?? '' }}" required>
            <button type="submit" class="btn btn-secondary">{{ __('author.reply_save') }}</button>
          </form>
          <p><small>{{ __('author.reply_edit_hint') }}</small></p>
        </li>
      @endforeach
    </ul>
  @endif
@endsection
