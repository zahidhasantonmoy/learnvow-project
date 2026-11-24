<div>
  @if($priced)
    <p>{{ __('print.unit_price') }}: ৳{{ number_format($priced['unit_price_bdt']) }}</p>
    <p>{{ __('print.delivery_charge') }}: ৳{{ number_format($priced['delivery_charge_bdt']) }}</p>
    <p><strong>{{ __('print.total') }}: ৳{{ number_format($priced['total_bdt']) }}</strong></p>
  @endif

  @if($error)
    <p class="alert">{{ $error }}</p>
  @endif

  <form wire:submit.prevent="submit">
    <p>
      <label for="quantity">{{ __('print.quantity') }} *</label><br>
      <input type="number" id="quantity" wire:model.live="quantity" min="1" max="10" required>
      <br><small>{{ __('print.quantity_hint') }}</small>
    </p>
    <p>
      <label for="delivery_name">{{ __('print.delivery_name') }} *</label><br>
      <input type="text" id="delivery_name" wire:model="delivery_name" maxlength="255" required>
    </p>
    <p>
      <label for="delivery_phone">{{ __('print.delivery_phone') }} *</label><br>
      <input type="text" id="delivery_phone" wire:model="delivery_phone" maxlength="32" required>
    </p>
    <p>
      <label for="delivery_address">{{ __('print.delivery_address') }} *</label><br>
      <textarea id="delivery_address" wire:model="delivery_address" required></textarea>
    </p>
    <p>
      <label for="district">{{ __('print.district') }}</label><br>
      <input type="text" id="district" wire:model="district" maxlength="100">
    </p>
    <p><button type="submit" class="btn">{{ __('print.place_order') }}</button></p>
  </form>
</div>
