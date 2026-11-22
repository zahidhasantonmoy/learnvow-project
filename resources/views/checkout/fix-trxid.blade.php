@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.fix_trxid_title') }}</h1>
  <p>{{ __('billing.order_no') }}: {{ $order->order_no }} — {{ __('billing.total_to_pay') }}: ৳{{ $order->total_bdt }}</p>
  @if($payment)
    <p>{{ __('billing.fix_count', ['count' => $payment->fix_count]) }}</p>
    @if($payment->fix_count >= 3)
      <p>{{ __('billing.fix_limit_reached') }}</p>
    @endif
  @endif
  <p>{{ __('billing.fix_trxid_hint') }}</p>
  @livewire('trx-id-form', ['order' => $order])
@endsection
