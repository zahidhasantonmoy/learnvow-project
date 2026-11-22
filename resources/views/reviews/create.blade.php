@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.write_review') }} — {{ $book->title }}</h1>
  <form method="POST" action="{{ route('reviews.store', ['locale' => app()->getLocale(), 'slug' => $book->slug]) }}">
    @csrf
    <div class="form-field">
      <label for="rating">{{ __('books.rating') }}</label>
      <select id="rating" name="rating">
        @for($i = 1; $i <= 5; $i++)
          <option value="{{ $i }}" @selected(old('rating', 5) == $i)>{{ $i }} ★</option>
        @endfor
      </select>
      @error('rating') <span class="error">{{ $message }}</span> @enderror
    </div>
    <div class="form-field">
      <label for="title">{{ __('books.review_title_label') }}</label>
      <input id="title" type="text" name="title" value="{{ old('title') }}">
      @error('title') <span class="error">{{ $message }}</span> @enderror
    </div>
    <div class="form-field">
      <label for="body">{{ __('books.review_body_label') }}</label>
      <textarea id="body" name="body" rows="4">{{ old('body') }}</textarea>
      @error('body') <span class="error">{{ $message }}</span> @enderror
    </div>
    @error('review') <p class="error">{{ $message }}</p> @enderror
    <button type="submit" class="btn">{{ __('books.submit_review') }}</button>
  </form>
@endsection
