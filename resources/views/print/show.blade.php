@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ $order->order_no }}</h1>
  <p><a href="{{ route('print-orders.index', ['locale' => app()->getLocale()]) }}">{{ __('print.back_to_orders') }}</a></p>

  <p>{{ __('print.book') }}: {{ $order->book->title ?? '' }}</p>
  <p>{{ __('print.quantity') }}: {{ $order->quantity }} × ৳{{ number_format($order->unit_price_bdt) }}</p>
  <p>{{ __('print.delivery_charge') }}: ৳{{ number_format($order->delivery_charge_bdt) }}</p>
  <p><strong>{{ __('print.total') }}: ৳{{ number_format($order->total_bdt) }}</strong></p>
  <p>{{ __('print.status') }}: {{ __('print.status_'.$order->status) }}</p>
  @if($order->tracking_no)
    <p>{{ __('print.tracking_no') }}: {{ $order->tracking_no }}</p>
  @endif
  @if($order->admin_note)
    <p>{{ __('print.admin_note') }}: {{ $order->admin_note }}</p>
  @endif

  @if($order->status === 'ordered')
    <h2>{{ __('print.submit_trxid') }}</h2>
    <form method="POST" action="{{ route('print-orders.trxid', ['locale' => app()->getLocale(), 'printOrder' => $order->id]) }}">
      @csrf
      <p>
        <label for="method">{{ __('print.payment_method') }} *</label><br>
        <select id="method" name="method" required>
          <option value="bkash">bKash</option>
          <option value="nagad">Nagad</option>
        </select>
      </p>
      <p>
        <label for="trx_id">{{ __('print.trx_id') }} *</label><br>
        <input type="text" id="trx_id" name="trx_id" maxlength="32" required>
      </p>
      <p><button type="submit" class="btn">{{ __('print.submit_trxid') }}</button></p>
    </form>

    <form method="POST" action="{{ route('print-orders.cancel', ['locale' => app()->getLocale(), 'printOrder' => $order->id]) }}">
      @csrf
      <p><button type="submit" class="btn">{{ __('print.cancel') }}</button></p>
    </form>
  @endif
@endsection
