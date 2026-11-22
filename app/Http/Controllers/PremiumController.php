<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Services\BillingService;
use Illuminate\Http\Request;

class PremiumController extends Controller
{
    use BuildsSeo;

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
