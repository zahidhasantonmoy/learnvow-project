<?php

namespace App\Policies;

use App\Models\Faq;
use App\Models\User;

/**
 * Builder C — Phase 3 spec §3.5, §12. FAQ entries.
 * Support / Admin / Super Admin manage (faqs.manage, spec §12).
 */
class FaqPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }

    public function view(User $user, Faq $faq): bool
    {
        return $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }

    public function update(User $user, Faq $faq): bool
    {
        return $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }

    public function delete(User $user, Faq $faq): bool
    {
        return $user->hasRole('super-admin');
    }
}
