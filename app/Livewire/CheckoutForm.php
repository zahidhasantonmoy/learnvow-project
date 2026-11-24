<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\Order;
use App\Models\SubscriptionPlan;
use App\Services\BillingService;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Checkout form (spec §10): server-priced totals, coupon apply with
 * live recalculation, TrxID submit. Never trusts client-sent prices.
 */
class CheckoutForm extends Component
{
    public string $type = 'book';
    public ?int $bookId = null;
    public ?int $planId = null;
    public string $couponCode = '';
    public int $walletUse = 0;
    public bool $useWallet = false;
    public string $method = 'bkash';
    public string $trxId = '';
    public ?string $error = null;
    public ?string $couponMessage = null;

    public function mount(string $type, ?int $bookId = null, ?int $planId = null): void
    {
        $this->type = $type;
        $this->bookId = $bookId;
        $this->planId = $planId;
    }

    public function priced(BillingService $billing): array
    {
        // C6: the wallet checkbox spends up to the full amount due.
        // priceOrder() itself caps at min(balance, amount due), so the
        // server never trusts the checkbox value — it recomputes.
        return $billing->priceOrder([
            'user' => auth()->user(),
            'type' => $this->type,
            'book_id' => $this->bookId,
            'plan_id' => $this->planId,
            'coupon_code' => $this->couponCode,
            'wallet_use_bdt' => $this->useWallet ? PHP_INT_MAX : $this->walletUse,
        ]);
    }

    public function applyCoupon(BillingService $billing): void
    {
        $this->couponMessage = null;
        $priced = $this->priced($billing);
        if ($this->couponCode !== '' && $priced['coupon_amt_bdt'] === 0) {
            $this->couponMessage = __('billing.coupon_invalid');
            $this->couponCode = '';
        }
    }

    public function submit(BillingService $billing)
    {
        $this->error = null;
        $user = auth()->user();

        // AUD3-02: validate a supplied TrxID BEFORE creating the order —
        // a bad TrxID used to leave an orphan pending_payment order.
        // (An empty TrxID is allowed: the order is created and the user
        // submits the TrxID on the fix-TrxID page.)
        if (trim($this->trxId) !== '' && ! BillingService::isValidTrxId($this->trxId)) {
            $this->error = __('billing.trxid_invalid');

            return null;
        }

        try {
            $priced = $this->priced($billing);
            $order = $billing->createOrder($user, $priced);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
            return null;
        }

        if ($order->total_bdt === 0) {
            return redirect()->route('payment.success', ['locale' => app()->getLocale(), 'order' => $order->id]);
        }

        if (trim($this->trxId) === '') {
            return redirect()->route('payment.fix-trxid.edit', ['locale' => app()->getLocale(), 'order' => $order->id]);
        }

        try {
            $billing->submitTrxId($order, $this->method, $this->trxId);
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
            return null;
        }

        return redirect()->route('orders.show', ['locale' => app()->getLocale(), 'order' => $order->id]);
    }

    public function render(BillingService $billing)
    {
        $book = $this->bookId ? Book::find($this->bookId) : null;
        $plan = $this->planId ? SubscriptionPlan::find($this->planId) : $billing->premiumPlan();

        return view('livewire.checkout-form', [
            'book' => $book,
            'plan' => $plan,
            'priced' => $this->priced($billing),
            'bkashNumber' => $billing->setting('payment.bkash_number', ''),
            'nagadNumber' => $billing->setting('payment.nagad_number', ''),
        ]);
    }
}
