@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.order_detail') }}</h1>
  <p>{{ __('billing.order_no') }}: {{ $order->order_no }}</p>
  <p>{{ __('billing.order_status') }}: {{ $order->status }}</p>
  <p>{{ __('billing.subtotal') }}: ৳{{ $order->subtotal_bdt }}</p>
  @if($order->coupon_amt_bdt > 0)
    <p>{{ __('billing.coupon_discount') }}: −৳{{ $order->coupon_amt_bdt }}</p>
  @endif
  @if($order->wallet_used_bdt > 0)
    <p>{{ __('billing.wallet_used') }}: −৳{{ $order->wallet_used_bdt }}</p>
  @endif
  <p><strong>{{ __('billing.total_to_pay') }}: ৳{{ $order->total_bdt }}</strong></p>
  <ul>
    @foreach($order->items as $item)
      <li>{{ $item->title_snapshot }} — ৳{{ $item->unit_price_bdt }}</li>
    @endforeach
  </ul>
  @foreach($order->payments as $payment)
    <p>{{ __('billing.trx_id') }}: {{ $payment->trx_id }} ({{ $payment->method }}, {{ $payment->status }})</p>
    @if($payment->fix_count > 0)
      <p>{{ __('billing.fix_count', ['count' => $payment->fix_count]) }}</p>
    @endif
  @endforeach
  @if(in_array($order->status, ['awaiting_verification', 'failed'], true))
    <p><a class="btn" href="{{ route('payment.fix-trxid.edit', ['locale' => app()->getLocale(), 'order' => $order->id]) }}">{{ __('billing.fix_trxid_title') }}</a></p>
  @endif
  @if($order->status === 'paid' && $order->refund_status === 'none')
    <form method="POST" action="{{ route('orders.refund', ['locale' => app()->getLocale(), 'order' => $order->id]) }}">
      @csrf
      <p>{{ __('billing.refund_to_wallet') }}</p>
      <button type="submit" class="btn">{{ __('billing.refund_request') }}</button>
    </form>
  @elseif($order->refund_status !== 'none')
    <p>{{ __('billing.refund_under_review') }}</p>
  @endif
  <h2>{{ __('billing.timeline') }}</h2>
  <ul>
    @foreach($order->payments as $payment)
      @foreach($payment->events as $event)
        <li>{{ $event->created_at?->format('Y-m-d H:i') }} — {{ $event->action }}@if($event->note) — {{ $event->note }}@endif</li>
      @endforeach
    @endforeach
  </ul>
@endsection
