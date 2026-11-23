<?php

namespace App\Livewire;

use App\Models\SupportTicket;
use Livewire\Component;

/**
 * Builder C — open a support ticket (spec §11). Used on the reader's
 * support/chat page (Builder A's view). A stuck payment is urgent.
 */
class SupportTicketForm extends Component
{
    public string $subject = '';

    public string $category = 'other';

    public string $body = '';

    public ?string $createdTicketNo = null;

    public function submit(): void
    {
        $user = auth()->user();

        if (! $user) {
            $this->addError('form', __('support.tickets.open_ticket'));

            return;
        }

        $validated = $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:payment,book,technical,account,other'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            // Round 2 minor: the required-subject error in the page
            // locale, not Laravel's English default (M6 pattern).
            'subject.required' => __('support.tickets.subject_required'),
        ]);

        $ticket = SupportTicket::query()->create([
            'user_id' => $user->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['category'] === 'payment' ? 'urgent' : 'normal',
        ]);

        $ticket->messages()->create([
            'user_id' => $user->id,
            'body' => $validated['body'],
        ]);

        $this->createdTicketNo = $ticket->ticket_no;
        $this->reset(['subject', 'body']);
        $this->category = 'other';

        // Minor: after creating a ticket the list must show it — redirect
        // straight to the new thread instead of leaving the stale
        // "no tickets" list on screen until a manual reload.
        $this->redirectRoute('support.chat.show', [
            'locale' => app()->getLocale(),
            'ticket' => $ticket->id,
        ]);
    }

    public function render()
    {
        return view('livewire.support-ticket-form');
    }
}
