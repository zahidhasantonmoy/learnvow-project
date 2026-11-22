<div>
  @if($book)
    <p>{{ $book->title }} — ৳{{ $book->price_bdt }}@if($book->list_price_bdt) <s>৳{{ $book->list_price_bdt }}</s>@endif</p>
  @elseif($plan)
    <p>{{ $plan->name }} — ৳{{ $plan->price_bdt }}</p>
  @endif
  <p>{{ __('billing.subtotal') }}: ৳{{ $priced['subtotal_bdt'] }}</p>
  @if($priced['coupon_amt_bdt'] > 0)
    <p>{{ __('billing.coupon_discount') }}: −৳{{ $priced['coupon_amt_bdt'] }}</p>
  @endif
  @if($priced['wallet_used_bdt'] > 0)
    <p>{{ __('billing.wallet_used') }}: −৳{{ $priced['wallet_used_bdt'] }}</p>
  @endif
  <p><strong>{{ __('billing.total_to_pay') }}: ৳{{ $priced['total_bdt'] }}</strong></p>

  <div>
    <label>{{ __('billing.coupon_code') }}
      <input type="text" wire:model="couponCode">
    </label>
    <button type="button" wire:click="applyCoupon">{{ __('billing.coupon_apply') }}</button>
    @if($couponMessage)<p>{{ $couponMessage }}</p>@endif
  </div>

  @if($priced['total_bdt'] > 0)
    <p>{{ __('billing.send_money_to') }}:
      @if($method === 'bkash')
        {{ $bkashNumber ?: __('billing.payment_number_not_set') }}
      @else
        {{ $nagadNumber ?: __('billing.payment_number_not_set') }}
      @endif
    </p>
    <div>
      <label>{{ __('billing.payment_method') }}
        <select wire:model="method">
          <option value="bkash">{{ __('billing.bkash') }}</option>
          <option value="nagad">{{ __('billing.nagad') }}</option>
        </select>
      </label>
      <label>{{ __('billing.trx_id') }}
        <input type="text" wire:model="trxId" placeholder="TrxID">
      </label>
      <p>{{ __('billing.trx_id_hint') }}</p>
    </div>
  @endif

  @if($error)<p>{{ $error }}</p>@endif
  <button type="button" wire:click="submit">{{ __('billing.submit_trxid') }}</button>
</div>
