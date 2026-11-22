@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.privacy_title') }}</h1>
  <p>{{ __('books.privacy_intro') }}</p>
  <h2>{{ __('books.privacy_collect_heading') }}</h2>
  <p>{{ __('books.privacy_collect_body') }}</p>
  <h2>{{ __('books.privacy_use_heading') }}</h2>
  <p>{{ __('books.privacy_use_body') }}</p>
  <h2>{{ __('books.privacy_rights_heading') }}</h2>
  <p>{{ __('books.privacy_rights_body') }}</p>
@endsection
