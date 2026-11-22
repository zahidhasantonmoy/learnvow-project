<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WalletTransaction;

class WalletPolicy
{
    public function view(User $user, WalletTransaction $transaction): bool
    {
        return $transaction->user_id === $user->id;
    }
}
