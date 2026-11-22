<?php

namespace App\Livewire;

use App\Services\BillingService;
use InvalidArgumentException;
use Livewire\Component;

/** Premium management: trial start, cancel (runs to end of term). */
class SubscriptionManager extends Component
{
    public ?string $error = null;
    public ?string $notice = null;

    public function startTrial(BillingService $billing): void
    {
        try {
            $billing->startTrial(auth()->user());
            $this->notice = __('billing.trial_started');
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function cancel(BillingService $billing): void
    {
        $subscription = $billing->currentSubscription(auth()->user());
        if ($subscription === null) {
            return;
        }
        $this->authorize('cancel', $subscription);
        $billing->cancelSubscription($subscription);
        $this->notice = __('billing.subscription_cancelled');
    }

    public function render(BillingService $billing)
    {
        $user = auth()->user();

        return view('livewire.subscription-manager', [
            'subscription' => $billing->currentSubscription($user),
            'plan' => $billing->premiumPlan(),
            'hasPremium' => $billing->hasActivePremium($user),
        ]);
    }
}
