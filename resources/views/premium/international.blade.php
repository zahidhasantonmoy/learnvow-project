@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.intl_title') }}</h1>
  <p>{{ __('billing.intl_subtitle') }}</p>
  <p><strong>{{ __('billing.premium_price', ['price' => $price]) }}</strong> · {{ __('billing.premium_trial', ['days' => $plan->trial_days ?? 7]) }}</p>
  <p>{{ __('billing.intl_payment_note') }}</p>
  @if($hasPremium)
    <p>{{ __('billing.premium_active') }}</p>
    <p><a href="{{ route('subscription.show', ['locale' => app()->getLocale()]) }}">{{ __('billing.subscription_manage') }}</a></p>
  @else
    <p><a class="btn" href="{{ route('checkout.premium.international', ['locale' => app()->getLocale()]) }}">{{ __('billing.premium_buy') }}</a></p>
  @endif
@endsection
