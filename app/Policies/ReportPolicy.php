<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

/**
 * Builder C — Phase 3 spec §5.2, §12. Reports against books / reviews.
 * Support may view and hide; only Admin / Super Admin decide.
 */
class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }

    public function view(User $user, Report $report): bool
    {
        return $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }

    public function create(User $user): bool
    {
        return true; // Any logged-in user may report a book or review.
    }

    public function decide(User $user, Report $report): bool
    {
        return $user->hasAnyRole(['admin', 'super-admin']);
    }

    public function delete(User $user, Report $report): bool
    {
        return false;
    }
}
