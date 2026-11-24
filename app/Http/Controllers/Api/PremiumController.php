<?php

namespace App\Http\Controllers\Api;

use App\Services\BillingService;
use Illuminate\Http\Request;

/** Premium status + trial (spec §1.3, §3.1). */
class PremiumController extends ApiController
{
    public function show(Request $request, BillingService $billing)
    {
        $user = $request->user();
        $subscription = $billing->currentSubscription($user);
        $plan = $billing->premiumPlan();
        $intl = $billing->internationalPlan();

        return $this->ok([
            'has_premium' => $billing->hasActivePremium($user),
            'plan' => $plan ? $this->planArray($plan) : null,
            'international_plan' => $intl ? $this->planArray($intl) : null,
            'subscription' => $subscription ? [
                'id' => $subscription->id, 'status' => $subscription->status,
                'plan_slug' => $subscription->plan?->slug,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'past_due_at' => $subscription->past_due_at?->toIso8601String(),
            ] : null,
        ]);
    }

    public function trial(Request $request, BillingService $billing)
    {
        try {
            $subscription = $billing->startTrial($request->user());
        } catch (\InvalidArgumentException $e) {
            return $this->fail($e->getMessage(), 422);
        }

        return $this->ok([
            'subscription_id' => $subscription->id,
            'status' => $subscription->status,
            'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
        ], 201);
    }

    private function planArray(\App\Models\SubscriptionPlan $plan): array
    {
        return [
            'id' => $plan->id, 'slug' => $plan->slug, 'name' => $plan->name,
            'price_bdt' => (int) $plan->price_bdt, 'duration_days' => (int) $plan->duration_days,
            'trial_days' => (int) $plan->trial_days, 'is_active' => (bool) $plan->is_active,
        ];
    }
}
