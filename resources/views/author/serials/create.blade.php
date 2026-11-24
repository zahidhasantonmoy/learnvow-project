@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.serial_create_title') }}</h1>
  <form method="POST" action="{{ route('author.serials.store', ['locale' => app()->getLocale()]) }}">
    @csrf
    <p>
      <label>{{ __('author.serial_title') }} *<br>
        <input type="text" name="title" value="{{ old('title') }}" maxlength="255" required>
      </label>
      @error('title') <span>{{ $message }}</span> @enderror
    </p>
    <p>
      <label>{{ __('author.serial_title_en') }}<br>
        <input type="text" name="title_en" value="{{ old('title_en') }}" maxlength="255">
      </label>
    </p>
    <p>
      <label>{{ __('author.serial_description') }}<br>
        <textarea name="description" rows="4">{{ old('description') }}</textarea>
      </label>
    </p>
    <p><label><input type="checkbox" name="is_free" value="1" checked> {{ __('author.serial_free') }}</label></p>
    <p><button type="submit" class="btn">{{ __('author.serial_create') }}</button></p>
  </form>
@endsection
