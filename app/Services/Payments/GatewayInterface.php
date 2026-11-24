<?php

namespace App\Services\Payments;

use App\Models\Order;

/** Payment gateway seam (spec §11.1). Only 'manual' exists in Phase 4. */
interface GatewayInterface
{
    /** Gateway name: 'manual' (later: 'sslcommerz', 'stripe', 'paddle'). */
    public function name(): string;

    /** Start a payment. Manual: TrxID instructions, never a redirect. */
    public function createPayment(Order $order): array;

    /** Verify a gateway callback. Manual has no callbacks: always false. */
    public function verifyCallback(array $payload): bool;

    /** Manual refunds are Super Admin only (Phase 2): false. */
    public function supportsRefunds(): bool;
}
