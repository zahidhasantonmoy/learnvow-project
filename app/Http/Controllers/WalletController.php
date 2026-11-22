<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Order;
use App\Services\BillingService;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    use BuildsSeo;

    public function index(string $locale, BillingService $billing)
    {
        $user = auth()->user();

        return view('wallet.index', [
            'user' => $user,
            'transactions' => \App\Models\WalletTransaction::where('user_id', $user->id)->latest()->paginate(20),
            'referralCredit' => $billing->referralCreditTotal($user),
            'seo' => $this->seo(__('wallet.title'), null, ['noindex' => true]),
        ]);
    }

    /** Top-up via manual TrxID: creates a wallet_topup order + payment. */
    public function topup(string $locale, Request $request, BillingService $billing)
    {
        $data = $request->validate([
            'amount_bdt' => 'required|integer|min:10',
            'method' => 'required|in:bkash,nagad',
            'trx_id' => 'required|string|max:32',
        ]);

        $user = auth()->user();
        $priced = $billing->priceOrder(['user' => $user, 'type' => 'wallet_topup', 'topup_amount_bdt' => $data['amount_bdt']]);
        $order = $billing->createOrder($user, $priced);

        try {
            $billing->submitTrxId($order, $data['method'], $data['trx_id']);
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['trx_id' => $e->getMessage()])->withInput();
        }

        return redirect()->route('wallet.index', ['locale' => app()->getLocale()])
            ->with('status', __('billing.topup_submitted'));
    }

    /** Saved payments: manual Send Money has no saved cards/auto-renew (spec §3.4). */
    public function saved()
    {
        return view('wallet.saved', [
            'seo' => $this->seo(__('wallet.saved_title'), null, ['noindex' => true]),
        ]);
    }
}
