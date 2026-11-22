<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $payment->user_id === $user->id;
    }

    /** Correcting a TrxID is the owner's job, while it is still fixable. */
    public function update(User $user, Payment $payment): bool
    {
        return $payment->user_id === $user->id
            && in_array($payment->status, ['awaiting_verification', 'failed'], true);
    }
}
