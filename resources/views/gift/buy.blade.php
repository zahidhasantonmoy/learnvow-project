@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.gift_title') }}</h1>
  <p>{{ $book->title }} — ৳{{ $book->price_bdt }}</p>
  <p>{{ __('billing.gift_note') }}</p>
  @livewire('checkout-form', ['type' => 'gift', 'bookId' => $book->id])
@endsection
