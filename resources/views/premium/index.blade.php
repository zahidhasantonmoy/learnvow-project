@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.premium_title') }}</h1>
  <div class="card">
  <p>{{ __('billing.premium_subtitle') }}</p>
  <p><strong>{{ __('billing.premium_price', ['price' => $price]) }}</strong> · {{ __('billing.premium_trial', ['days' => $plan->trial_days ?? 7]) }}</p>
  <p>{{ __('billing.premium_benefits') }}</p>
  @if($hasPremium)
    <p>{{ __('billing.premium_active') }}</p>
    <p><a href="{{ route('subscription.show', ['locale' => app()->getLocale()]) }}">{{ __('billing.subscription_manage') }}</a></p>
  @else
    <p><a class="btn" href="{{ route('checkout.premium', ['locale' => app()->getLocale()]) }}">{{ __('billing.premium_buy') }}</a></p>
    @livewire('subscription-manager')
  @endif
  </div>
@endsection
