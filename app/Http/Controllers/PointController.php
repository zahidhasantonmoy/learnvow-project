<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\PointTransaction;
use App\Services\BillingService;

class PointController extends Controller
{
    use BuildsSeo;

    public function index(string $locale, BillingService $billing)
    {
        $user = auth()->user();

        return view('wallet.points', [
            'user' => $user,
            'transactions' => PointTransaction::where('user_id', $user->id)->latest()->paginate(20),
            'perPurchase' => $billing->settingInt('points.per_purchase', 0),
            'perFinish' => $billing->settingInt('points.per_finish', 0),
            'seo' => $this->seo(__('wallet.points_title'), null, ['noindex' => true]),
        ]);
    }
}
