@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('wallet.title') }}</h1>
  <p>{{ __('wallet.balance') }}: <strong>৳{{ $user->wallet_balance_bdt }}</strong></p>
  @if($referralCredit > 0)
    <p>{{ __('wallet.referral_credit', ['amount' => $referralCredit]) }}</p>
  @endif
  <p>{{ __('wallet.referral_credit_note') }}</p>
  @livewire('wallet-topup')
  <h2>{{ __('wallet.transactions') }}</h2>
  @if($transactions->isEmpty())
    <p>{{ __('wallet.no_transactions') }}</p>
  @else
    <ul>
      @foreach($transactions as $tx)
        <li>{{ $tx->created_at?->format('Y-m-d H:i') }} — {{ __('wallet.type_'.$tx->type) }}: {{ $tx->amount_bdt > 0 ? '+' : '' }}৳{{ $tx->amount_bdt }} ({{ __('wallet.balance') }} ৳{{ $tx->balance_after_bdt }})</li>
      @endforeach
    </ul>
    {{ $transactions->links() }}
  @endif
@endsection
