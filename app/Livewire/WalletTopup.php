<?php

namespace App\Livewire;

use App\Services\BillingService;
use InvalidArgumentException;
use Livewire\Component;

/** Wallet top-up via manual TrxID (spec §3.4). */
class WalletTopup extends Component
{
    public int $amount = 100;
    public string $method = 'bkash';
    public string $trxId = '';
    public ?string $error = null;

    public function submit(BillingService $billing)
    {
        $this->error = null;
        $user = auth()->user();

        try {
            $priced = $billing->priceOrder(['user' => $user, 'type' => 'wallet_topup', 'topup_amount_bdt' => $this->amount]);
            $order = $billing->createOrder($user, $priced);
            $billing->submitTrxId($order, $this->method, $this->trxId);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
            return null;
        }

        return redirect()->route('wallet.index', ['locale' => app()->getLocale()]);
    }

    public function render(BillingService $billing)
    {
        return view('livewire.wallet-topup', [
            'bkashNumber' => $billing->setting('payment.bkash_number', ''),
            'nagadNumber' => $billing->setting('payment.nagad_number', ''),
        ]);
    }
}
