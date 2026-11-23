@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('payouts.pool_title') }}</h1>

  <h2>{{ __('payouts.pool_rules_heading') }}</h2>
  <ol>
    <li>{{ __('payouts.pool_rule_1', ['share' => $sharePct, 'keep' => 100 - $sharePct]) }}</li>
    <li>{{ __('payouts.pool_rule_2', ['minutes' => $minMinutes, 'percent' => $minPercent]) }}</li>
    <li>{{ __('payouts.pool_rule_3') }}</li>
    <li>{{ __('payouts.pool_rule_4') }}</li>
    <li>{{ __('payouts.pool_rule_5', ['min' => $minPayout, 'day' => $payoutDay]) }}</li>
  </ol>

  <h2>{{ __('payouts.pool_example_heading') }}</h2>
  <p>{{ __('payouts.pool_example_note') }}</p>
  <table>
    <tbody>
      <tr>
        <td>{{ __('payouts.pool_example_subscribers') }}</td>
        <td>{{ $example['subscribers'] }} × ৳{{ $example['price_bdt'] }}</td>
      </tr>
      <tr>
        <td>{{ __('payouts.pool_example_gross') }}</td>
        <td>৳{{ $example['gross_bdt'] }}</td>
      </tr>
      <tr>
        <td>{{ __('payouts.pool_example_fee') }}</td>
        <td>৳{{ $example['net_bdt'] }}</td>
      </tr>
      <tr>
        <td>{{ __('payouts.pool_example_pool', ['share' => $example['share_pct']]) }}</td>
        <td>৳{{ $example['pool_bdt'] }}</td>
      </tr>
      <tr>
        <td>{{ __('payouts.pool_example_share', ['pct' => $example['example_minutes_pct']]) }}</td>
        <td>৳{{ $example['example_share_bdt'] }}</td>
      </tr>
    </tbody>
  </table>

  <h2>{{ __('payouts.pool_my_minutes') }} ({{ $period }})</h2>
  @if($myMinutes['counted_minutes'] > 0)
    <p>{{ __('payouts.pool_my_minutes_value', ['counted' => $myMinutes['counted_minutes'], 'total' => $myMinutes['minutes'], 'readers' => $myMinutes['readers']]) }}</p>
  @else
    <p>{{ __('payouts.pool_my_minutes_none') }}</p>
  @endif
@endsection
