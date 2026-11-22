<div>
  @if($payment && $payment->fix_count >= 3)
    <p>{{ __('billing.fix_limit_reached') }}</p>
  @else
    <label>{{ __('billing.payment_method') }}
      <select wire:model="method">
        <option value="bkash">{{ __('billing.bkash') }}</option>
        <option value="nagad">{{ __('billing.nagad') }}</option>
      </select>
    </label>
    <label>{{ __('billing.trx_id') }}
      <input type="text" wire:model="trxId" placeholder="TrxID">
    </label>
    @if($error)<p>{{ $error }}</p>@endif
    <button type="button" wire:click="submit">{{ __('billing.fix_trxid_submit') }}</button>
  @endif
</div>
