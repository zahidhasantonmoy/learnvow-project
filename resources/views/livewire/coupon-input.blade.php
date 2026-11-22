<div>
  <label>{{ __('billing.coupon_code') }}
    <input type="text" wire:model="code">
  </label>
  <button type="button" wire:click="check">{{ __('billing.coupon_apply') }}</button>
  @if($message)<p>{{ $message }}</p>@endif
  @if($discount > 0)
    <p>{{ __('billing.coupon_discount') }}: −৳{{ $discount }}</p>
  @endif
</div>
