<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Builder C — Phase 3 spec §7.6. Support ticket (reader opens; Support /
 * Admin / Super Admin reply in the same thread).
 */
class SupportTicket extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_WAITING_REPLY = 'waiting_reply';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ESCALATED = 'escalated';

    protected $fillable = [
        'ticket_no', 'user_id', 'subject', 'category', 'status', 'priority',
        'assigned_to', 'escalated_to', 'escalated_at',
    ];

    // DB defaults apply on insert; these keep a fresh in-memory instance
    // consistent (e.g. Livewire mount reading $ticket->status).
    protected $attributes = [
        'category' => 'other',
        'status' => 'open',
        'priority' => 'normal',
    ];

    protected function casts(): array
    {
        return [
            'escalated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            if (empty($ticket->ticket_no)) {
                $ticket->ticket_no = static::nextTicketNo();
            }
        });
    }

    /** Next sequential number for the current year: LV-T-2026-0001. */
    public static function nextTicketNo(?int $year = null): string
    {
        $year ??= (int) now()->format('Y');
        $prefix = 'LV-T-'.$year.'-';

        $last = static::query()
            ->where('ticket_no', 'like', $prefix.'%')
            ->orderByDesc('ticket_no')
            ->value('ticket_no');

        $serial = 0;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $m)) {
            $serial = (int) $m[1];
        }

        return $prefix.str_pad((string) ($serial + 1), 4, '0', STR_PAD_LEFT);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id')->orderBy('created_at');
    }

    /** Messages the reader may see (internal staff notes excluded). */
    public function visibleMessages(): HasMany
    {
        return $this->messages()->where('is_internal', false);
    }

    public function isEscalated(): bool
    {
        return $this->status === self::STATUS_ESCALATED || $this->escalated_to !== null;
    }
}
