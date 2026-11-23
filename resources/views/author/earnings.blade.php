@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('payouts.earnings_title') }}</h1>
  <p>{{ __('payouts.total_balance') }}: <strong>৳{{ number_format((int) $balance) }}</strong></p>
  <p>{{ __('payouts.sales_total') }}: <strong>৳{{ number_format((int) $salesTotal) }}</strong></p>
  <p>{{ __('payouts.pool_total') }}: <strong>৳{{ number_format((int) $poolTotal) }}</strong></p>
  <p>{{ __('payouts.next_payout') }}: {{ $nextPayout->format('Y-m-d') }}</p>

  @livewire('payout-request')

  <h2>{{ __('payouts.earnings_history') }}</h2>
  @if($earnings->isEmpty())
    <p>{{ __('payouts.no_earnings') }}</p>
  @else
    <table>
      <thead>
        <tr>
          <th>{{ __('payouts.col_date') }}</th>
          <th>{{ __('payouts.col_book') }}</th>
          <th>{{ __('payouts.col_type') }}</th>
          <th>{{ __('payouts.col_period') }}</th>
          <th>{{ __('payouts.col_amount') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($earnings as $earning)
          <tr>
            <td>{{ $earning->created_at?->format('Y-m-d') }}</td>
            <td>{{ $earning->book?->title ?? '—' }}</td>
            <td>{{ __('payouts.type_'.$earning->type) }}</td>
            <td>{{ $earning->period }}</td>
            <td>{{ $earning->amount_bdt > 0 ? '+' : '' }}৳{{ number_format((int) $earning->amount_bdt) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    {{ $earnings->links() }}
  @endif

  <h2>{{ __('payouts.payout_history') }}</h2>
  @if($payouts->isEmpty())
    <p>{{ __('payouts.no_payouts') }}</p>
  @else
    <table>
      <thead>
        <tr>
          <th>{{ __('payouts.col_period') }}</th>
          <th>{{ __('payouts.col_sales') }}</th>
          <th>{{ __('payouts.col_pool') }}</th>
          <th>{{ __('payouts.col_total') }}</th>
          <th>{{ __('payouts.col_method') }}</th>
          <th>{{ __('payouts.col_status') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($payouts as $payoutRow)
          <tr>
            <td>{{ $payoutRow->period }}</td>
            <td>৳{{ number_format((int) $payoutRow->sales_bdt) }}</td>
            <td>৳{{ number_format((int) $payoutRow->pool_bdt) }}</td>
            <td>৳{{ number_format((int) $payoutRow->total_bdt) }}</td>
            <td>{{ __('payouts.method_'.$payoutRow->method) }} {{ $payoutRow->maskedDestination() }}</td>
            <td>{{ __('payouts.status_'.$payoutRow->status) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    {{ $payouts->links() }}
  @endif
@endsection
