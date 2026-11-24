<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Builder C — reader-side support chat (spec §9.1, §3.5).
 *
 * Routes live in routes/author.php (Builder A) — this controller only
 * serves them. Views are Builder A's support/chat.blade.php; the
 * variables this controller passes are:
 *   index: $tickets (the reader's own tickets, newest first)
 *   show:  $ticket  (the thread; the blade renders <livewire:ticket-thread>)
 */
class SupportChatController extends Controller
{
    /**
     * The reader's own tickets — or, for the support role, the open
     * ticket queue (AUD3-05: staff previously saw only "my tickets"
     * and had to know a ticket's URL to work it).
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->hasRole('support')) {
            $tickets = SupportTicket::query()
                ->whereIn('status', [
                    SupportTicket::STATUS_OPEN,
                    SupportTicket::STATUS_IN_PROGRESS,
                    SupportTicket::STATUS_WAITING_REPLY,
                    SupportTicket::STATUS_ESCALATED,
                ])
                ->orderByDesc('created_at')
                ->paginate(20);

            return view('support.chat', ['tickets' => $tickets, 'isQueue' => true]);
        }

        $tickets = SupportTicket::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('support.chat', ['tickets' => $tickets, 'isQueue' => false]);
    }

    /** Open a new ticket (first message = the opening text). */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'category' => ['required', 'in:payment,book,technical,account,other'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            // Round 2 minor: required-subject error in the page locale.
            'subject.required' => __('support.tickets.subject_required'),
        ]);

        $ticket = SupportTicket::query()->create([
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            // A stuck payment is urgent (spec §7.6).
            'priority' => $validated['category'] === 'payment' ? 'urgent' : 'normal',
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return redirect()
            ->route('support.chat.show', ['locale' => app()->getLocale(), 'ticket' => $ticket->id])
            ->with('status', __('support.tickets.opened'));
    }

    /**
     * One ticket thread (owner only — staff use the Filament resource).
     *
     * NOTE: $locale must be declared first — route parameters are passed
     * positionally, so without it the 'bn'/'en' value would land in $ticket
     * (same convention as Builder A's controllers).
     */
    public function show(string $locale, Request $request, SupportTicket $ticket): View
    {
        Gate::authorize('view', $ticket);

        return view('support.chat', ['ticket' => $ticket]);
    }

    /** Reply in the thread (owner only). */
    public function reply(string $locale, Request $request, SupportTicket $ticket): RedirectResponse
    {
        Gate::authorize('reply', $ticket);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:1', 'max:5000'],
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        if (in_array($ticket->status, ['waiting_reply', 'resolved', 'closed'], true)) {
            $ticket->status = 'in_progress';
            $ticket->save();
        }

        return back()->with('status', __('support.tickets.replied'));
    }
}
