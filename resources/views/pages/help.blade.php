@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.help_title') }}</h1>
  <p>{{ __('books.help_intro') }}</p>
  <h2>{{ __('books.help_reading_heading') }}</h2>
  <p>{{ __('books.help_reading_body') }}</p>
  <h2>{{ __('books.help_payment_heading') }}</h2>
  <p>{{ __('books.help_payment_body') }}</p>
  <h2>{{ __('books.help_contact_heading') }}</h2>
  <p>{{ __('books.help_contact_body') }}</p>
@endsection
