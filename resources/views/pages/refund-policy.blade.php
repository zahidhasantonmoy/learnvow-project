@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.refund_title') }}</h1>
  <p>{{ __('books.refund_intro') }}</p>
  <h2>{{ __('books.refund_when_heading') }}</h2>
  <p>{{ __('books.refund_when_body') }}</p>
  <h2>{{ __('books.refund_how_heading') }}</h2>
  <p>{{ __('books.refund_how_body') }}</p>
@endsection
