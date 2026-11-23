@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.print_title') }} — {{ $book->title }}</h1>
  <p>{{ __('author.print_note') }}</p>
  <form method="POST" action="{{ route('author.print-settings.update', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}">
    @csrf
    @method('PUT')
    <p>
      <label><input type="checkbox" name="allow_pod" value="1" @checked($book->allow_pod)> {{ __('author.allow_pod') }}</label>
    </p>
    <p>
      <label><input type="checkbox" name="allow_licensed_pdf" value="1" @checked($book->allow_licensed_pdf)> {{ __('author.allow_licensed_pdf') }}</label>
    </p>
    <p>
      <label for="print_price_bdt">{{ __('author.print_price_bdt') }} ({{ __('author.optional') }})</label><br>
      <input type="number" id="print_price_bdt" name="print_price_bdt" min="0" value="{{ $book->print_price_bdt }}">
    </p>
    <p><button type="submit" class="btn">{{ __('author.save') }}</button></p>
  </form>
  <p><a href="{{ route('author.books', ['locale' => app()->getLocale()]) }}">{{ __('author.back_to_studio') }}</a></p>
@endsection
