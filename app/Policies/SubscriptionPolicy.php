<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;

class SubscriptionPolicy
{
    public function view(User $user, Subscription $subscription): bool
    {
        return $subscription->user_id === $user->id;
    }

    public function cancel(User $user, Subscription $subscription): bool
    {
        return $subscription->user_id === $user->id
            && in_array($subscription->status, ['trialing', 'active', 'past_due'], true);
    }
}
