<?php

namespace App\Policies;

use App\Models\PrintOrder;
use App\Models\User;

/** Print orders: own order, the assigned partner's order, or Admin. */
class PrintOrderPolicy
{
    public function view(User $user, PrintOrder $order): bool
    {
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return true;
        }

        if ((int) $order->user_id === (int) $user->id) {
            return true;
        }

        return $order->partner_id !== null && (int) $order->partner_id === (int) $user->id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['reader', 'author']);
    }

    /** Buyer cancels before confirmation; Admin cancels any open order. */
    public function cancel(User $user, PrintOrder $order): bool
    {
        if ($user->hasAnyRole(['admin', 'super-admin'])) {
            return ! $order->isTerminal();
        }

        return (int) $order->user_id === (int) $user->id && $order->isCancellableByBuyer();
    }

    /** Admin confirms / sends to partner / marks delivered. */
    public function manage(User $user, PrintOrder $order): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    /** The assigned partner fulfils their own order (printing / shipped). */
    public function fulfill(User $user, PrintOrder $order): bool
    {
        return $order->partner_id !== null && (int) $order->partner_id === (int) $user->id;
    }
}
