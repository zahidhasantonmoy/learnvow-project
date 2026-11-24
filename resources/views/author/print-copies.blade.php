@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('print.author_copies_heading') }}</h1>
  <p><a href="{{ route('author.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('author.back_to_studio') }}</a></p>
  <p>{{ __('print.author_copies_note') }}</p>

  <form method="POST" action="{{ route('author.print-copies.store', ['locale' => app()->getLocale()]) }}">
    @csrf
    <p>
      <label for="book_id">{{ __('print.book') }} *</label><br>
      <select id="book_id" name="book_id" required>
        @foreach($books as $book)
          <option value="{{ $book->id }}">{{ $book->title }}</option>
        @endforeach
      </select>
    </p>
    <p>
      <label for="quantity">{{ __('print.quantity') }} *</label><br>
      <input type="number" id="quantity" name="quantity" min="1" max="10" value="1" required>
    </p>
    <p>
      <label for="delivery_name">{{ __('print.delivery_name') }} *</label><br>
      <input type="text" id="delivery_name" name="delivery_name" maxlength="255" required>
    </p>
    <p>
      <label for="delivery_phone">{{ __('print.delivery_phone') }} *</label><br>
      <input type="text" id="delivery_phone" name="delivery_phone" maxlength="32" required>
    </p>
    <p>
      <label for="delivery_address">{{ __('print.delivery_address') }} *</label><br>
      <textarea id="delivery_address" name="delivery_address" required></textarea>
    </p>
    <p>
      <label for="district">{{ __('print.district') }}</label><br>
      <input type="text" id="district" name="district" maxlength="100">
    </p>
    <p><button type="submit" class="btn">{{ __('print.place_order') }}</button></p>
  </form>
@endsection
