@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.checkout_title') }}</h1>
  <div class="card">
  @livewire('checkout-form', ['type' => $type, 'bookId' => $book?->id, 'planId' => $plan?->id])
  </div>
@endsection
