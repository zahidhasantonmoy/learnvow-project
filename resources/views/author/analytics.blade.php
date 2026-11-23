@extends('layouts.app')
@section('content')
  <h1 class="serif">{{ __('payouts.analytics_title') }}</h1>
  <p>{{ __('payouts.analytics_total_minutes') }}: <strong>{{ $totalMinutes }}</strong></p>
  <p>{{ __('payouts.analytics_counted_minutes') }}: <strong>{{ $countedMinutes }}</strong></p>

  @if($byBook->isEmpty())
    <p>{{ __('payouts.analytics_no_data') }}</p>
  @else
    <table>
      <thead>
        <tr>
          <th>{{ __('payouts.analytics_col_book') }}</th>
          <th>{{ __('payouts.analytics_col_readers') }}</th>
          <th>{{ __('payouts.analytics_col_minutes') }}</th>
          <th>{{ __('payouts.analytics_col_counted') }}</th>
          <th>{{ __('payouts.analytics_col_finished') }}</th>
          <th>{{ __('payouts.analytics_col_avg_percent') }}</th>
        </tr>
      </thead>
      <tbody>
        @foreach($byBook as $row)
          <tr>
            <td>{{ $row->book?->title ?? '—' }}</td>
            <td>{{ $row->readers }}</td>
            <td>{{ $row->total_minutes }}</td>
            <td>{{ $row->counted_minutes }}</td>
            <td>{{ $progress[$row->book_id]->finished ?? 0 }}</td>
            <td>{{ $progress[$row->book_id] ? round($progress[$row->book_id]->avg_percent, 1) : 0 }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
@endsection
