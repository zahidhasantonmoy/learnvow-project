@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.payment_success') }}</h1>
  <p>{{ __('billing.order_no') }}: {{ $order->order_no }}</p>
  <p>{{ __('billing.total_to_pay') }}: ৳{{ $order->total_bdt }}</p>
  <ul>
    @foreach($order->items as $item)
      <li>{{ $item->title_snapshot }} — ৳{{ $item->unit_price_bdt }}</li>
    @endforeach
  </ul>
  <p><a href="{{ route('orders.show', ['locale' => app()->getLocale(), 'order' => $order->id]) }}">{{ __('billing.order_detail') }}</a></p>
@endsection
