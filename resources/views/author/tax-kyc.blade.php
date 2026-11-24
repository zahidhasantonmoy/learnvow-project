@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.tax_kyc_heading') }}</h1>
  <p><a href="{{ route('author.dashboard', ['locale' => app()->getLocale()]) }}">{{ __('author.back_to_studio') }}</a></p>
  <p>{{ __('author.tax_kyc_note') }}</p>

  @if($profile->exists)
    <p>{{ __('author.tax_kyc_status') }}: {{ __('author.tax_kyc_status_'.$profile->status) }}</p>
    @if($profile->review_note)
      <p>{{ __('author.tax_kyc_review_note') }}: {{ $profile->review_note }}</p>
    @endif
  @endif

  <form method="POST" action="{{ route('author.tax-kyc.update', ['locale' => app()->getLocale()]) }}">
    @csrf
    @method('PUT')
    <p>
      <label for="legal_name">{{ __('author.tax_kyc_legal_name') }} *</label><br>
      <input type="text" id="legal_name" name="legal_name" maxlength="255" value="{{ old('legal_name', $profile->legal_name) }}" required>
    </p>
    <p>
      <label for="nid_no">{{ __('author.tax_kyc_nid') }} *</label><br>
      <input type="text" id="nid_no" name="nid_no" maxlength="64" value="" placeholder="{{ $profile->maskedNid() }}" {{ $profile->nid_no ? '' : 'required' }}>
      @if($profile->nid_no)
        <br><small>{{ $profile->maskedNid() }}</small>
      @endif
    </p>
    <p>
      <label for="tin_no">{{ __('author.tax_kyc_tin') }}</label><br>
      <input type="text" id="tin_no" name="tin_no" maxlength="64" value="" placeholder="{{ $profile->maskedTin() }}">
    </p>
    <p>
      <label for="address">{{ __('author.tax_kyc_address') }}</label><br>
      <textarea id="address" name="address">{{ old('address', $profile->address) }}</textarea>
    </p>
    <p>
      <label for="bank_account_name">{{ __('author.tax_kyc_bank_name') }}</label><br>
      <input type="text" id="bank_account_name" name="bank_account_name" maxlength="255" value="{{ old('bank_account_name', $profile->bank_account_name) }}">
    </p>
    <p>
      <label for="bank_account_number">{{ __('author.tax_kyc_bank_account') }}</label><br>
      <input type="text" id="bank_account_number" name="bank_account_number" maxlength="64" value="" placeholder="{{ $profile->maskedBankAccount() }}">
      @if($profile->bank_account_number)
        <br><small>{{ $profile->maskedBankAccount() }}</small>
      @endif
    </p>
    <p><button type="submit" class="btn">{{ __('author.tax_kyc_heading') }}</button></p>
  </form>
@endsection
