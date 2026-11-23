@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.settings_title') }}</h1>
  <p>{{ __('author.settings_share_note') }}</p>
  <form method="POST" action="{{ route('author.settings.update', ['locale' => app()->getLocale()]) }}">
    @csrf
    @method('PUT')
    <p>
      <label for="pen_name">{{ __('author.pen_name') }} ({{ __('author.optional') }})</label><br>
      <input type="text" id="pen_name" name="pen_name" maxlength="100" value="{{ $author->pen_name }}">
    </p>
    <p>
      <label for="bio">{{ __('author.bio') }}</label><br>
      <textarea id="bio" name="bio" rows="4" maxlength="2000">{{ $author->bio }}</textarea>
    </p>
    <p>
      <label for="bio_en">{{ __('author.bio_en') }} ({{ __('author.optional') }})</label><br>
      <textarea id="bio_en" name="bio_en" rows="4" maxlength="2000">{{ $author->bio_en }}</textarea>
    </p>
    <p>
      {{ __('author.current_bkash') }}: <strong>{{ $maskedBkash }}</strong>
      <br>
      <label for="bkash_number">{{ __('author.bkash_number') }} ({{ __('author.optional') }})</label><br>
      <input type="text" id="bkash_number" name="bkash_number" maxlength="20" autocomplete="off">
      <small>{{ __('author.bkash_hint') }}</small>
    </p>
    <p>
      <label for="payout_method">{{ __('author.payout_method') }}</label><br>
      <select id="payout_method" name="payout_method">
        <option value="bkash" @selected($author->payout_method === 'bkash')>{{ __('author.payout_bkash') }}</option>
        <option value="bank" @selected($author->payout_method === 'bank')>{{ __('author.payout_bank') }}</option>
      </select>
    </p>
    <p>
      <label for="bank_name">{{ __('author.bank_name') }} ({{ __('author.optional') }})</label><br>
      <input type="text" id="bank_name" name="bank_name" maxlength="100" value="{{ $author->bank_name }}">
    </p>
    <p>
      <label for="bank_account_number">{{ __('author.bank_account_number') }} ({{ __('author.optional') }})</label><br>
      <input type="text" id="bank_account_number" name="bank_account_number" maxlength="50" autocomplete="off">
    </p>
    <p><button type="submit" class="btn">{{ __('author.save') }}</button></p>
  </form>
@endsection
