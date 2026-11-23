<div>
  <p>{{ __('payouts.request_balance', ['amount' => number_format((int) ($balance ?? 0))]) }}</p>
  @if($nextPayout)
    <p>{{ __('payouts.request_next', ['date' => $nextPayout]) }}</p>
  @endif
  @if($requested)
    <p>{{ __('payouts.request_sent') }}</p>
  @else
    <button type="button" wire:click="submit">{{ __('payouts.request_button') }}</button>
  @endif
  <p>{{ __('payouts.request_note') }}</p>
</div>
