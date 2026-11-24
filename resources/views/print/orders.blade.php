@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('print.orders_title') }}</h1>

  @if($orders->isEmpty())
    <p>{{ __('print.orders_empty') }}</p>
  @else
    <ul>
      @foreach($orders as $order)
        <li>
          <a href="{{ route('print-orders.show', ['locale' => app()->getLocale(), 'printOrder' => $order->id]) }}">{{ $order->order_no }}</a>
          — {{ $order->book->title ?? '' }}
          — {{ __('print.status_'.$order->status) }}
          — ৳{{ number_format($order->total_bdt) }}
        </li>
      @endforeach
    </ul>
    {{ $orders->links() }}
  @endif
@endsection
