@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('billing.referral_title') }}</h1>
  <p>{{ __('billing.referral_code') }}: <strong>{{ $user->referral_code }}</strong></p>
  <p>{{ __('billing.referral_reward') }}:
    @if($rewardType === 'wallet_credit')
      ৳{{ $rewardValue }}
    @else
      {{ __('billing.premium_trial', ['days' => $rewardValue]) }}
    @endif
  </p>
  <p>{{ __('billing.referral_qualified_note') }}</p>
  <ul>
    @foreach($referrals as $referral)
      <li>{{ $referral->code_used }} — {{ $referral->status }}</li>
    @endforeach
  </ul>
@endsection
