@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.subscription_manage') }}</h1>
  @if($subscription)
    <p>{{ __('billing.premium_title') }} — {{ $subscription->status }}</p>
    @if($subscription->status === 'trialing' && $subscription->trial_ends_at)
      <p>{{ __('billing.trial_ends', ['date' => $subscription->trial_ends_at->format('Y-m-d')]) }}</p>
    @else
      <p>{{ __('billing.premium_expires', ['date' => $subscription->ends_at->format('Y-m-d')]) }}</p>
    @endif
    @if(in_array($subscription->status, ['trialing', 'active', 'past_due'], true))
      <p><a class="btn" href="{{ route('checkout.premium', ['locale' => app()->getLocale()]) }}">{{ __('billing.premium_renew') }}</a></p>
      <form method="POST" action="{{ route('subscription.cancel', ['locale' => app()->getLocale()]) }}">
        @csrf
        <p>{{ __('billing.cancel_note') }}</p>
        <button type="submit" class="btn">{{ __('billing.cancel_subscription') }}</button>
      </form>
    @endif
  @else
    <p>{{ __('billing.no_subscription') }}</p>
    <p><a class="btn" href="{{ route('checkout.premium', ['locale' => app()->getLocale()]) }}">{{ __('billing.premium_buy') }}</a></p>
  @endif
  @livewire('subscription-manager')
@endsection
