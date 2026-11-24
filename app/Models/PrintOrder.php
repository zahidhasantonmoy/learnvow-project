<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Manual print / POD order (Phase 4 spec §2, §12.1.4).
 *
 * Flow: ordered → confirmed (TrxID verified) → sent_to_partner →
 * printing → shipped → delivered / cancelled. Money is int (৳);
 * unit price is snapshotted at order time. Author share accrues only
 * on delivered, via PayoutService::accruePrintSale() — never here.
 */
class PrintOrder extends Model
{
    public const STATUS_ORDERED = 'ordered';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_SENT_TO_PARTNER = 'sent_to_partner';

    public const STATUS_PRINTING = 'printing';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ORDERED,
        self::STATUS_CONFIRMED,
        self::STATUS_SENT_TO_PARTNER,
        self::STATUS_PRINTING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'order_no', 'user_id', 'book_id', 'author_id', 'quantity',
        'unit_price_bdt', 'delivery_charge_bdt', 'total_bdt',
        'partner_cost_bdt', 'partner_payment_trx_id', 'status', 'payment_id',
        'payment_method', 'payment_trx_id',
        'delivery_name', 'delivery_phone', 'delivery_address', 'district',
        'partner_id', 'tracking_no', 'admin_note', 'ordered_by_author',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price_bdt' => 'integer',
            'delivery_charge_bdt' => 'integer',
            'total_bdt' => 'integer',
            'partner_cost_bdt' => 'integer',
            'ordered_by_author' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_DELIVERED, self::STATUS_CANCELLED], true);
    }

    /** Buyer may cancel only before the order is confirmed (spec §2.1). */
    public function isCancellableByBuyer(): bool
    {
        return $this->status === self::STATUS_ORDERED;
    }
}
