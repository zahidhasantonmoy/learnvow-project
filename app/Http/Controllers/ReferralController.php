<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Referral;
use App\Services\BillingService;

class ReferralController extends Controller
{
    use BuildsSeo;

    public function index(string $locale, BillingService $billing)
    {
        $user = auth()->user();
        if (empty($user->referral_code)) {
            $user->update(['referral_code' => 'LV'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT)]);
            $user->refresh();
        }

        return view('referral.index', [
            'user' => $user,
            'referrals' => Referral::where('referrer_id', $user->id)->latest()->get(),
            'rewardType' => $billing->setting('referral.reward_type', 'premium_week'),
            'rewardValue' => $billing->settingInt('referral.reward_value', 7),
            'seo' => $this->seo(__('billing.referral_title'), null, ['noindex' => true]),
        ]);
    }
}
