@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.terms_title') }}</h1>
  <p>{{ __('books.terms_intro') }}</p>
  <h2>{{ __('books.terms_accounts_heading') }}</h2>
  <p>{{ __('books.terms_accounts_body') }}</p>
  <h2>{{ __('books.terms_purchases_heading') }}</h2>
  <p>{{ __('books.terms_purchases_body') }}</p>
  <h2>{{ __('books.terms_content_heading') }}</h2>
  <p>{{ __('books.terms_content_body') }}</p>
@endsection
