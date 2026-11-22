@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.payment_title') }}</h1>
  <p><a href="{{ route('wallet.index', ['locale' => app()->getLocale()]) }}">{{ __('wallet.title') }}: ৳{{ $user->wallet_balance_bdt }}</a></p>
  @if($referralCredit > 0)
    <p>{{ __('wallet.referral_credit', ['amount' => $referralCredit]) }}</p>
  @endif
  @if($subscription)
    <p>{{ __('billing.premium_title') }}: {{ $subscription->status }} — {{ __('billing.premium_expires', ['date' => $subscription->ends_at->format('Y-m-d')]) }}</p>
    <p><a href="{{ route('subscription.show', ['locale' => app()->getLocale()]) }}">{{ __('billing.subscription_manage') }}</a></p>
  @endif
  <h2>{{ __('billing.my_orders') }}</h2>
  <ul>
    @foreach($orders as $order)
      <li><a href="{{ route('orders.show', ['locale' => app()->getLocale(), 'order' => $order->id]) }}">{{ $order->order_no }}</a> — ৳{{ $order->total_bdt }} — {{ $order->status }}</li>
    @endforeach
  </ul>
  <p><a href="{{ route('orders.index', ['locale' => app()->getLocale()]) }}">{{ __('billing.my_orders') }}</a></p>
@endsection
