<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database (Phase 1 order — spec §6).
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            CategorySeeder::class,
            AuthorSeeder::class,
            BookSeeder::class,
            // Phase 2 — Builder A: demo EPUB files for the seeded books.
            BookFileSeeder::class,
            // DemoUserSeeder LAST for Phase 1 links; Phase 2's BillingSeeder
            // comes after it (demo orders/payments need the demo users).
            DemoUserSeeder::class,
            // Phase 2 — Builder B: plan, EID26, gift code, wallet/points,
            // demo orders, payments, subscription.
            BillingSeeder::class,
            // Phase 3 (spec §8.5) — Builder B: demo author earnings + payout
            // history + pool settings keys.
            AuthorEarningsSeeder::class,
            // Phase 3 (spec §8.5) — Builder C: the 5 historical audit rows.
            AuditLogSeeder::class,
        ]);
    }
}
