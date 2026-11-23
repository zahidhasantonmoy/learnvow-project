<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

/**
 * Builder C — Phase 3 spec §3.5, §12. Support tickets.
 *
 * Staff (Support / Admin / Super Admin) see all tickets and may reply;
 * a reader sees only their own tickets. Tickets are never deleted.
 * Registered explicitly in AppServiceProvider (the model is
 * SupportTicket, the policy is TicketPolicy — Laravel's name-guess
 * cannot find it).
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return (int) $ticket->user_id === (int) $user->id
            || $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }

    public function create(User $user): bool
    {
        return true; // Any logged-in reader may open a ticket.
    }

    public function reply(User $user, SupportTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }

    /** Escalation is a staff action (Support escalates up, Admin re-assigns). */
    public function escalate(User $user, SupportTicket $ticket): bool
    {
        return $user->hasAnyRole(['support', 'admin', 'super-admin']);
    }

    public function delete(User $user, SupportTicket $ticket): bool
    {
        return false;
    }
}
