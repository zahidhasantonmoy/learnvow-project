<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Author tax / KYC profile (Phase 4 spec §3.3, §12.1.14).
 * One profile per author. NID / TIN / bank account are encrypted casts;
 * pages must never show the full numbers — last 3 digits only (see
 * maskedNid() / maskedBankAccount()).
 */
class TaxKycProfile extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_VERIFIED, self::STATUS_REJECTED];

    protected $fillable = [
        'author_id', 'legal_name', 'nid_no', 'tin_no', 'address',
        'bank_account_name', 'bank_account_number', 'status',
        'reviewed_by', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'nid_no' => 'encrypted',
            'tin_no' => 'encrypted',
            'bank_account_number' => 'encrypted',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Last 3 digits only — never render a full NID. */
    public function maskedNid(): string
    {
        return $this->maskLast3($this->nid_no);
    }

    /** Last 3 digits only — never render a full bank account number. */
    public function maskedBankAccount(): string
    {
        return $this->maskLast3($this->bank_account_number);
    }

    public function maskedTin(): string
    {
        return $this->maskLast3($this->tin_no);
    }

    protected function maskLast3(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        return $digits === '' ? '' : '•••'.substr($digits, -3);
    }
}
