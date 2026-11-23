<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Builder C — Phase 3 spec §7.9. Copyright takedown claim.
 * deadline_at is the 48h first-response TARGET (assumption A9), set when
 * the claim is created.
 */
class TakedownRequest extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_AWAITING_PROOF = 'awaiting_proof';

    public const STATUS_ACTION_TAKEN = 'action_taken';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'book_id', 'claimant_name', 'claimant_email', 'reason', 'proof_path',
        'status', 'deadline_at', 'reviewed_by', 'decision_note',
    ];

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'deadline_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (TakedownRequest $request) {
            if (empty($request->deadline_at)) {
                $request->deadline_at = now()->addHours(48);
            }
        });
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Whether the 48h first-response target has passed without a decision. */
    public function isOverdue(): bool
    {
        return $this->deadline_at !== null
            && in_array($this->status, [self::STATUS_OPEN, self::STATUS_AWAITING_PROOF], true)
            && $this->deadline_at->isPast();
    }
}
