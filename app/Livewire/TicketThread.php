<?php

namespace App\Livewire;

use App\Models\SupportTicket;
use App\Notifications\AuthorNotification;
use App\Services\AdminService;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Builder C — ticket thread (spec §11). Reply, change status, escalate.
 *
 * The reader sees only non-internal messages; staff see everything
 * including internal notes. Escalation sets escalated_to admin /
 * super_admin + escalated_at and moves the ticket to `escalated`.
 */
class TicketThread extends Component
{
    public SupportTicket $ticket;

    public string $body = '';

    public bool $isInternal = false;

    public string $status = 'open';

    public function mount(SupportTicket $ticket): void
    {
        $this->ticket = $ticket;
        $this->status = $ticket->status;
    }

    public function isStaff(): bool
    {
        $user = auth()->user();

        return $user !== null && AdminService::userHasRole($user, ['support', 'admin', 'super-admin']);
    }

    public function messages()
    {
        $query = $this->ticket->messages()->with('user')->orderBy('created_at');

        if (! $this->isStaff()) {
            $query->where('is_internal', false);
        }

        return $query->get();
    }

    public function reply(): void
    {
        $user = auth()->user();

        if (! $user) {
            return;
        }

        $this->authorize('reply', $this->ticket);

        $validated = $this->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $this->ticket->messages()->create([
            'user_id' => $user->id,
            'body' => $validated['body'],
            'is_internal' => $this->isStaff() && $this->isInternal,
        ]);

        // Phase 3 §6: a staff reply notifies the ticket owner (in-app
        // only). Internal notes never notify; the owner's own replies
        // never notify themselves.
        if ($this->isStaff() && ! $this->isInternal && (int) $this->ticket->user_id !== (int) $user->id) {
            $owner = $this->ticket->user;

            if ($owner !== null && Schema::hasTable('notifications')) {
                $owner->notify(new AuthorNotification('ticket_reply', [
                    'ticket_id' => $this->ticket->id,
                    'ticket_no' => $this->ticket->ticket_no,
                ]));
            }
        }

        // A reply re-opens the conversation: staff replies move an open
        // ticket to in_progress; a reader reply does the same when staff
        // were waiting (or the ticket was resolved/closed).
        if (in_array($this->ticket->status, ['open', 'waiting_reply', 'resolved', 'closed', 'escalated'], true)) {
            $this->ticket->status = 'in_progress';
            $this->ticket->save();
        }

        $this->reset(['body', 'isInternal']);
        $this->ticket->refresh();
        $this->status = $this->ticket->status;
    }

    public function changeStatus(string $status): void
    {
        $this->authorize('update', $this->ticket);

        if (! in_array($status, ['open', 'in_progress', 'waiting_reply', 'resolved', 'closed'], true)) {
            return;
        }

        $this->ticket->status = $status;
        $this->ticket->save();
        $this->ticket->refresh();
        $this->status = $status;
    }

    public function escalate(string $to): void
    {
        $this->authorize('escalate', $this->ticket);

        if (! in_array($to, ['admin', 'super_admin'], true)) {
            return;
        }

        $this->ticket->status = 'escalated';
        $this->ticket->escalated_to = $to;
        $this->ticket->escalated_at = now();
        $this->ticket->save();

        AdminService::writeAudit(auth()->user(), 'ticket.escalated', 'support_tickets', $this->ticket->getKey(), [
            'message' => 'Escalated ticket '.$this->ticket->ticket_no.' to '.$to,
        ]);

        $this->ticket->refresh();
        $this->status = $this->ticket->status;
    }

    public function render()
    {
        return view('livewire.ticket-thread', [
            'messages' => $this->messages(),
            'isStaff' => $this->isStaff(),
        ]);
    }
}
