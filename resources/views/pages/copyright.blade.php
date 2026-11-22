@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.copyright_title') }}</h1>
  <p>{{ __('books.copyright_intro') }}</p>
  <h2>{{ __('books.copyright_dmca_heading') }}</h2>
  <p>{{ __('books.copyright_dmca_body') }}</p>
  <h2>{{ __('books.copyright_owners_heading') }}</h2>
  <p>{{ __('books.copyright_owners_body') }}</p>
@endsection
