@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.my_orders') }}</h1>
  @if($orders->isEmpty())
    <p>{{ __('billing.no_subscription') }}</p>
  @else
    <ul>
      @foreach($orders as $order)
        <li>
          <a href="{{ route('orders.show', ['locale' => app()->getLocale(), 'order' => $order->id]) }}">{{ $order->order_no }}</a>
          — {{ $order->type }} — ৳{{ $order->total_bdt }} — {{ $order->status }}
        </li>
      @endforeach
    </ul>
    {{ $orders->links() }}
  @endif
@endsection
