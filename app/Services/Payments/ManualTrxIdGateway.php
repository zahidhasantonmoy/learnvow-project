<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Services\BillingService;

/** Manual bKash/Nagad TrxID gateway (spec §11.1) — the Phase 2 flow, unchanged. */
class ManualTrxIdGateway implements GatewayInterface
{
    public function __construct(protected BillingService $billing) {}

    public function name(): string
    {
        return 'manual';
    }

    public function createPayment(Order $order): array
    {
        return [
            'gateway' => 'manual',
            'instructions' => __('billing.send_money_to'),
            'bkash_number' => $this->billing->setting('payment.bkash_number', ''),
            'nagad_number' => $this->billing->setting('payment.nagad_number', ''),
            'amount_bdt' => (int) $order->total_bdt,
            'order_no' => $order->order_no,
        ];
    }

    public function verifyCallback(array $payload): bool
    {
        return false;
    }

    public function supportsRefunds(): bool
    {
        return false;
    }
}
