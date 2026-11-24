@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('print.partner_dashboard_heading') }}</h1>

  @if($orders->isEmpty())
    <p>{{ __('print.partner_orders_empty') }}</p>
  @else
    <ul>
      @foreach($orders as $order)
        <li>
          <strong>{{ $order->order_no }}</strong>
          — {{ $order->book->title ?? '' }}
          — {{ $order->quantity }} × ৳{{ number_format($order->unit_price_bdt) }}
          — {{ __('print.status_'.$order->status) }}
          @if($order->tracking_no)
            — {{ __('print.tracking_no') }}: {{ $order->tracking_no }}
          @endif
          @if(in_array($order->status, ['sent_to_partner', 'printing'], true))
            <form method="POST" action="{{ route('print-partner.orders.status', ['locale' => app()->getLocale(), 'printOrder' => $order->id]) }}" style="display:inline">
              @csrf
              @if($order->status === 'sent_to_partner')
                <input type="hidden" name="status" value="printing">
                <button type="submit" class="btn">{{ __('print.mark_printing') }}</button>
              @else
                <input type="hidden" name="status" value="shipped">
                <input type="text" name="tracking_no" maxlength="100" placeholder="{{ __('print.tracking_no') }}">
                <button type="submit" class="btn">{{ __('print.mark_shipped') }}</button>
              @endif
            </form>
          @endif
        </li>
      @endforeach
    </ul>
    {{ $orders->links() }}
  @endif
@endsection
