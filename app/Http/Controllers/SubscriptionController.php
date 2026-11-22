<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Subscription;
use Illuminate\Support\Facades\Gate;
use App\Services\BillingService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    use BuildsSeo;

    public function show(string $locale, BillingService $billing)
    {
        $user = auth()->user();
        $subscription = $billing->currentSubscription($user);
        $plan = $billing->premiumPlan();

        return view('premium.manage', [
            'subscription' => $subscription,
            'plan' => $plan,
            'hasPremium' => $billing->hasActivePremium($user),
            'seo' => $this->seo(__('billing.subscription_manage'), null, ['noindex' => true]),
        ]);
    }

    public function cancel(string $locale, Request $request, BillingService $billing)
    {
        $user = auth()->user();
        $subscription = $billing->currentSubscription($user);
        if ($subscription === null) {
            return redirect()->route('subscription.show', ['locale' => app()->getLocale()])
                ->with('status', __('billing.no_subscription'));
        }
        Gate::authorize('cancel', $subscription);
        $billing->cancelSubscription($subscription, (string) $request->input('reason', ''));

        return redirect()->route('subscription.show', ['locale' => app()->getLocale()])
            ->with('status', __('billing.subscription_cancelled'));
    }
}
