<div>
  @if($notice)<p>{{ $notice }}</p>@endif
  @if($error)<p>{{ $error }}</p>@endif
  @if(!$hasPremium && $subscription === null)
    <button type="button" wire:click="startTrial">{{ __('billing.premium_start_trial') }}</button>
  @elseif($subscription && in_array($subscription->status, ['trialing', 'active'], true))
    <button type="button" wire:click="cancel">{{ __('billing.cancel_subscription') }}</button>
  @endif
</div>
