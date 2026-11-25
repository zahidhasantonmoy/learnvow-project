<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AuthorEarningController extends Controller
{
    use BuildsSeo;

    /** Author — Earnings & Payouts (spec §10.1 authorEarnings). Sales + pool shown SEPARATELY. */
    public function index(string $locale, PayoutService $payout)
    {
        $author = $this->author();

        if ($author === null) {
            return redirect('/'.$locale.'/author/apply');
        }

        // Coordinator fix: the base Controller has no AuthorizesRequests
        // trait, so $this->authorize() fatals — Phase 1/2 use Gate::authorize.
        Gate::authorize('viewAny', AuthorEarning::class);

        $earnings = AuthorEarning::where('author_id', $author->id)
            ->with('book')->latest()->paginate(20);

        $salesTotal = (int) AuthorEarning::where('author_id', $author->id)
            ->whereIn('type', [AuthorEarning::TYPE_SALE, AuthorEarning::TYPE_ADJUSTMENT])
            ->sum('amount_bdt');
        $poolTotal = (int) AuthorEarning::where('author_id', $author->id)
            ->where('type', AuthorEarning::TYPE_POOL)
            ->sum('amount_bdt');

        $payouts = AuthorPayout::where('author_id', $author->id)
            ->latest('period')->paginate(12);

        $payoutDay = $payout->settingInt('pool.payout_day', 10);
        $nextPayout = now()->day <= $payoutDay
            ? now()->startOfMonth()->addDays($payoutDay - 1)
            : now()->addMonth()->startOfMonth()->addDays($payoutDay - 1);

        return view('author.earnings', [
            'author' => $author,
            'earnings' => $earnings,
            'salesTotal' => $salesTotal,
            'poolTotal' => $poolTotal,
            'balance' => $payout->authorBalance($author),
            'payouts' => $payouts,
            'nextPayout' => $nextPayout,
            'seo' => $this->seo(__('payouts.earnings_title'), null, ['noindex' => true]),
        ]);
    }

    /** Payout request (spec §2.4): a notice to the Super Admin — the monthly payout runs regardless. */
    public function requestPayout(string $locale, Request $request, PayoutService $payout)
    {
        $author = $this->author();

        if ($author === null) {
            return redirect('/'.$locale.'/author/apply');
        }

        Gate::authorize('viewAny', AuthorEarning::class);

        // Low-1 (role audit 2026-11-25): same ৳500 minimum as the
        // Livewire component — below it the request is rejected (no
        // notice) and the balance carries forward, never "sent".
        $minimum = $payout->minimumPayout();
        $balance = $payout->authorBalance($author);

        if ($balance < $minimum) {
            return back()->withErrors([
                'payout' => __('payouts.request_minimum', [
                    'min' => number_format($minimum),
                    'balance' => number_format($balance),
                ]),
            ]);
        }

        $payout->recordPayoutRequest($author, $request->user());

        return back()->with('status', __('payouts.request_sent'));
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
