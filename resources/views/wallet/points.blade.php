@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('wallet.points_title') }}</h1>
  <p>{{ __('wallet.points_balance') }}: <strong>{{ $user->points_balance }}</strong></p>
  <p>{{ __('wallet.points_note') }}</p>
  <p>{{ __('wallet.points_per_purchase', ['points' => $perPurchase]) }} · {{ __('wallet.points_per_finish', ['points' => $perFinish]) }}</p>
  <h2>{{ __('wallet.transactions') }}</h2>
  @if($transactions->isEmpty())
    <p>{{ __('wallet.no_transactions') }}</p>
  @else
    <ul>
      @foreach($transactions as $tx)
        <li>{{ $tx->created_at?->format('Y-m-d H:i') }} — {{ $tx->type }}: {{ $tx->points > 0 ? '+' : '' }}{{ $tx->points }} ({{ __('wallet.points_balance') }} {{ $tx->balance_after }})</li>
      @endforeach
    </ul>
    {{ $transactions->links() }}
  @endif
@endsection
