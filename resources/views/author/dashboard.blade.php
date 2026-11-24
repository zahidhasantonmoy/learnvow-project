@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.dashboard_title') }}</h1>

  <dl>
    <dt>{{ __('author.total_earnings') }}</dt>
    <dd>৳{{ number_format($data['total_bdt']) }}</dd>
    <dt>{{ __('author.sales_earnings') }}</dt>
    <dd>৳{{ number_format($data['sales_bdt']) }}</dd>
    <dt>{{ __('author.pool_earnings') }}</dt>
    <dd>৳{{ number_format($data['pool_bdt']) }}</dd>
    <dt>{{ __('author.next_payout') }}</dt>
    <dd>{{ $data['next_payout'] }}</dd>
  </dl>

  <p>
    <a href="{{ route('author.publish', ['locale' => app()->getLocale()]) }}" class="btn">{{ __('author.publish_book') }}</a>
    <a href="{{ route('author.serials.index', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('author.my_serials') }}</a>
    <a href="{{ route('author.books', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('author.my_books') }}</a>
    <a href="{{ route('author.reviews', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('author.my_reviews') }}</a>
    <a href="{{ route('author.earnings', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('author.my_earnings') }}</a>
    <a href="{{ route('author.pool', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('author.my_pool') }}</a>
    <a href="{{ route('author.analytics', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('author.my_analytics') }}</a>
    <a href="{{ route('author.promos', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('author.my_promos') }}</a>
    <a href="{{ route('author.settings', ['locale' => app()->getLocale()]) }}" class="btn btn-secondary">{{ __('author.my_settings') }}</a>
  </p>

  <h2>{{ __('author.recent_sales') }}</h2>
  @if($data['recent_sales']->isEmpty())
    <p>{{ __('author.recent_sales_empty') }}</p>
  @else
    <ul>
      @foreach($data['recent_sales'] as $sale)
        <li>
          {{ $sale->title }} · {{ $sale->quantity }} {{ __('author.copies') }} ·
          {{ __('author.your_share') }}: ৳{{ number_format((int) round($sale->unit_price_bdt * $data['share_pct'] / 100)) }} ·
          {{ $sale->country ?? '' }} · {{ $sale->created_at }}
        </li>
      @endforeach
    </ul>
  @endif
@endsection
