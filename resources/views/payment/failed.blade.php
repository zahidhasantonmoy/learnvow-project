@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.payment_failed') }}</h1>
  <p>{{ __('billing.payment_failed_note') }}</p>
  <p>{{ __('billing.order_no') }}: {{ $order->order_no }}</p>
  @if(in_array($order->status, ['awaiting_verification', 'failed'], true))
    <p><a class="btn" href="{{ route('payment.fix-trxid.edit', ['locale' => app()->getLocale(), 'order' => $order->id]) }}">{{ __('billing.fix_trxid_title') }}</a></p>
  @endif
@endsection
