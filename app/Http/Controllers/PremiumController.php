<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Services\BillingService;
use Illuminate\Http\Request;

class PremiumController extends Controller
{
    use BuildsSeo;

    /** International plan page (spec §3.1, §14.6): ৳199/month, manual TrxID only. */
    public function international(string $locale, BillingService $billing)
    {
        $plan = $billing->internationalPlan();
        $subscription = $billing->currentSubscription(auth()->user());
        $hasPremium = $billing->hasActivePremium(auth()->user());

        return view('premium.international', [
            'plan' => $plan,
            'price' => $plan ? (int) $plan->price_bdt : (int) $billing->setting('intl.price_bdt', '199'),
            'subscription' => $subscription,
            'hasPremium' => $hasPremium,
            'gateway' => $billing->setting('payments.gateway', 'manual'),
            'seo' => $this->seo(__('billing.intl_title'), null, ['noindex' => true]),
        ]);
    }

    public function index(string $locale, BillingService $billing)
    {
        $plan = $billing->premiumPlan();
        $subscription = $billing->currentSubscription(auth()->user());
        $hasPremium = $billing->hasActivePremium(auth()->user());

        return view('premium.index', [
            'plan' => $plan,
            'price' => $plan ? (int) $plan->price_bdt : (int) $billing->setting('subscription.price_bdt', '99'),
            'subscription' => $subscription,
            'hasPremium' => $hasPremium,
            'seo' => $this->seo(__('billing.premium_title'), null, ['noindex' => true]),
        ]);
    }
}
