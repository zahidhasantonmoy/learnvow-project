<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuthorPayout extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'author_id', 'period', 'sales_bdt', 'pool_bdt', 'total_bdt',
        'method', 'destination', 'status', 'payout_trx_id',
        'approved_by', 'approved_at', 'paid_at', 'failure_reason', 'retry_count',
    ];

    protected function casts(): array
    {
        return [
            'sales_bdt' => 'integer',
            'pool_bdt' => 'integer',
            'total_bdt' => 'integer',
            'destination' => 'encrypted',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** Masked destination for display, e.g. bKash ··210 — never the full number. */
    public function maskedDestination(): ?string
    {
        $destination = $this->destination;

        if ($destination === null || $destination === '') {
            return null;
        }

        return '··'.substr((string) $destination, -3);
    }
}
