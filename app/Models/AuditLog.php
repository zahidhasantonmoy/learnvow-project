<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Builder C — Phase 3 spec §7.14. Append-only audit log.
 *
 * A row is written once and never changed — not even by Super Admin
 * (spec §0.8, §4.5). The updating/deleting guards below refuse any
 * Eloquent update or delete; AuditLogPolicy denies them too. There is
 * no updated_at column on the table.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id', 'actor_role', 'action', 'subject_type', 'subject_id',
        'details', 'ip_address', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Append-only: refuse any update or delete through Eloquent.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
