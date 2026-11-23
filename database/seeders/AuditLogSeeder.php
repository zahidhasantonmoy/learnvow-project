<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Book;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Builder C — Phase 3 spec §8.3. The FIVE historical audit rows from the
 * prototype's superAudit screen, verbatim — never changed.
 *
 * "Today" = 2026-11-22, "Yesterday" = 2026-11-21. The spec gives no clock
 * time for the two yesterday rows, so they are seeded at noon; the date
 * and the text are what is frozen.
 *
 * Actors resolve to the demo accounts (01700000001 = admin,
 * 01700000000 = super-admin); the System row has actor NULL.
 * Idempotent: re-running the seeder does not duplicate the five rows.
 */
class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('phone', '01700000001')->first()
            ?? User::query()->whereHas('roles', fn ($q) => $q->where('name', 'admin'))->first();
        $superAdmin = User::query()->where('phone', '01700000000')->first()
            ?? User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super-admin'))->first();

        $padma = Book::query()->where('title', 'পদ্মা নদীর মাঝি')->first();
        $order1042 = Order::query()->where('order_no', 'LV-2026-1042')->first();

        $rows = [
            [
                'actor_id' => $admin?->getKey(),
                'actor_role' => 'admin',
                'action' => 'payment.verified',
                'subject_type' => 'orders',
                'subject_id' => $order1042?->getKey(),
                'details' => ['message' => 'Verified TrxID 9HX7K2LMNP — order LV-2026-1042 unlocked'],
                'created_at' => '2026-11-22 11:20:00',
            ],
            [
                'actor_id' => $admin?->getKey(),
                'actor_role' => 'admin',
                'action' => 'book.approved',
                'subject_type' => 'books',
                'subject_id' => $padma?->getKey(),
                'details' => ['message' => 'Approved book: পদ্মা নদীর মাঝি'],
                'created_at' => '2026-11-22 10:48:00',
            ],
            [
                'actor_id' => $superAdmin?->getKey(),
                'actor_role' => 'super-admin',
                'action' => 'payout.approved',
                'subject_type' => 'author_payouts',
                'subject_id' => null,
                'details' => ['message' => 'Approved author payout ৳18,460'],
                'created_at' => '2026-11-22 09:15:00',
            ],
            [
                'actor_id' => null,
                'actor_role' => null,
                'action' => 'security.failed_logins',
                'subject_type' => 'users',
                'subject_id' => $admin?->getKey(),
                'details' => ['message' => '3 failed logins on Admin account — alert sent'],
                'created_at' => '2026-11-21 12:00:00',
            ],
            [
                'actor_id' => $superAdmin?->getKey(),
                'actor_role' => 'super-admin',
                'action' => 'settings.changed',
                'subject_type' => 'settings',
                'subject_id' => null,
                'details' => ['message' => 'Changed Premium price: ৳99 (unchanged) / trial 7 days'],
                'created_at' => '2026-11-21 12:00:00',
            ],
        ];

        foreach ($rows as $row) {
            AuditLog::query()->firstOrCreate(
                ['action' => $row['action'], 'created_at' => $row['created_at']],
                $row,
            );
        }
    }
}
