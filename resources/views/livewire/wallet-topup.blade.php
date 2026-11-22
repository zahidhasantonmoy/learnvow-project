<div>
  <h2>{{ __('wallet.topup') }}</h2>
  <label>{{ __('wallet.topup_amount') }}
    <input type="number" wire:model="amount" min="10">
  </label>
  <label>{{ __('billing.payment_method') }}
    <select wire:model="method">
      <option value="bkash">{{ __('billing.bkash') }}</option>
      <option value="nagad">{{ __('billing.nagad') }}</option>
    </select>
  </label>
  <p>{{ __('billing.send_money_to') }}:
    @if($method === 'bkash') {{ $bkashNumber ?: __('billing.payment_number_not_set') }}
    @else {{ $nagadNumber ?: __('billing.payment_number_not_set') }} @endif
  </p>
  <label>{{ __('billing.trx_id') }}
    <input type="text" wire:model="trxId" placeholder="TrxID">
  </label>
  @if($error)<p>{{ $error }}</p>@endif
  <button type="button" wire:click="submit">{{ __('billing.submit_trxid') }}</button>
</div>
