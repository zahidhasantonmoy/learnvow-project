<?php

namespace App\Livewire;

use App\Models\Order;
use App\Services\BillingService;
use InvalidArgumentException;
use Livewire\Component;

/** Wrong-TrxID self-fix (X76): format check + 3-fix limit (spec §3.2). */
class TrxIdForm extends Component
{
    public Order $order;
    public string $method = 'bkash';
    public string $trxId = '';
    public ?string $error = null;

    public function mount(Order $order): void
    {
        $this->order = $order;
    }

    public function submit(BillingService $billing)
    {
        $this->error = null;
        $payment = $this->order->payments()->latest()->first();

        try {
            if ($payment === null) {
                $billing->submitTrxId($this->order->fresh(), $this->method, $this->trxId);
            } else {
                $this->authorize('update', $payment);
                $billing->correctTrxId($payment, $this->trxId);
            }
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
            return null;
        }

        return redirect()->route('orders.show', ['locale' => app()->getLocale(), 'order' => $this->order->id]);
    }

    public function render()
    {
        $payment = $this->order->payments()->latest()->first();

        return view('livewire.trx-id-form', ['payment' => $payment]);
    }
}
