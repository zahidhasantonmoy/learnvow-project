<?php

namespace App\Http\Controllers\Api;

use App\Models\WalletTransaction;
use App\Services\BillingService;
use Illuminate\Http\Request;

/** Wallet balance + referral credit (X13) + recent ledger (spec §1.3). */
class WalletController extends ApiController
{
    public function show(Request $request, BillingService $billing)
    {
        $user = $request->user()->fresh();
        $transactions = WalletTransaction::where('user_id', $user->id)
            ->latest()->limit(20)->get()->map(fn (WalletTransaction $t) => [
                'id' => $t->id, 'type' => $t->type, 'amount_bdt' => (int) $t->amount_bdt,
                'balance_after_bdt' => (int) $t->balance_after_bdt, 'note' => $t->note,
                'created_at' => $t->created_at?->toIso8601String(),
            ])->values();

        return $this->ok([
            'balance_bdt' => (int) ($user->wallet_balance_bdt ?? 0),
            'points_balance' => (int) ($user->points_balance ?? 0),
            'referral_credit_bdt' => $billing->referralCreditTotal($user),
            'transactions' => $transactions,
        ]);
    }
}
