<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Services\PayoutService;

class AuthorPoolController extends Controller
{
    use BuildsSeo;

    /** Author — How the Premium Pool Pays You (spec §10.1 authorPool). Rules + worked example render FROM SETTINGS, never hardcoded. */
    public function index(string $locale, PayoutService $payout)
    {
        $author = $this->author();

        if ($author === null) {
            return redirect('/'.$locale.'/author/apply');
        }

        $period = now()->format('Y-m');

        return view('author.pool', [
            'author' => $author,
            'sharePct' => $payout->sharePct(),
            'feeRatePct' => $payout->settingInt('pool.fee_rate_pct', 2),
            'minMinutes' => $payout->settingInt('pool.count_min_minutes', 10),
            'minPercent' => $payout->settingInt('pool.count_min_percent', 10),
            'minPayout' => $payout->settingInt('pool.min_payout_bdt', 500),
            'payoutDay' => $payout->settingInt('pool.payout_day', 10),
            'example' => $payout->workedExample(),
            'period' => $period,
            'myMinutes' => $payout->countedMinutesFor($author, $period),
            'seo' => $this->seo(__('payouts.pool_title'), null, ['noindex' => true]),
        ]);
    }

    protected function author(): ?Author
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        return Author::where('user_id', $user->id)->first();
    }
}
