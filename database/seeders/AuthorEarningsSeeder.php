<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Models\Book;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Phase 3 §8.1–§8.2 — demo author's earnings opening balance + payout
 * history + pool settings keys.
 *
 * Demo author = the AuthorSeeder/Phase 1 demo author on account
 * 01700000003 (LearnVow Commissioned Series). The §8.1 figures are the
 * prototype's FROZEN DEMO numbers, seeded as type=adjustment with the
 * note "demo opening balance (prototype frozen figures)" — they are NOT
 * real sales and can never be reconciled against real orders (e.g.
 * Gitanjali is a free book yet shows ৳8,000 of demo sales income).
 * Real earnings accrue only from real orders (§2.1) and real reading
 * (§2.2). The §8.4 model figures (৳322,500 collected etc.) and the
 * Tanvir/Nusrat payout rows are NOT seeded (A18).
 *
 * Run order (spec §8.5): after AuthorSeeder, BookSeeder, DemoUserSeeder.
 * The coordinator wires this seeder into DatabaseSeeder.
 */
class AuthorEarningsSeeder extends Seeder
{
    public function run(): void
    {
        $demoUser = User::where('phone', '01700000003')->first();
        $author = $demoUser !== null
            ? Author::where('user_id', $demoUser->id)->first()
            : null;
        $author ??= Author::where('slug', 'learnvow-commissioned-series')->first();

        if ($author === null) {
            return;
        }

        // ---- §8.1: demo opening balance (type=adjustment, 2026-11). ----
        $sales = [
            ['slug' => 'gitanjali', 'amount_bdt' => 8000],
            ['slug' => 'prompt-engineering-basics', 'amount_bdt' => 5600],
            ['slug' => 'the-prophet', 'amount_bdt' => 35040],
        ];

        foreach ($sales as $row) {
            $book = Book::where('slug', $row['slug'])->first();

            if ($book === null) {
                continue;
            }

            AuthorEarning::updateOrCreate(
                [
                    'author_id' => $author->id,
                    'book_id' => $book->id,
                    'period' => '2026-11',
                    'type' => AuthorEarning::TYPE_ADJUSTMENT,
                ],
                [
                    'order_id' => null,
                    'amount_bdt' => $row['amount_bdt'],
                    'share_pct' => 80,
                    'note' => 'demo opening balance (prototype frozen figures)',
                ]
            );
        }

        AuthorEarning::updateOrCreate(
            [
                'author_id' => $author->id,
                'book_id' => null,
                'period' => '2026-11',
                'type' => AuthorEarning::TYPE_POOL,
            ],
            [
                'order_id' => null,
                'amount_bdt' => 636,
                'share_pct' => 80,
                'note' => 'demo opening balance (prototype frozen figures)',
            ]
        );

        // ---- §8.2: payout history (destination ends ··210, masked on display). ----
        $payouts = [
            [
                'period' => '2026-09', 'sales_bdt' => 11980, 'pool_bdt' => 0,
                'total_bdt' => 11980, 'status' => AuthorPayout::STATUS_PAID,
                'payout_trx_id' => 'DEMOSEED01', 'paid_at' => '2026-09-10 10:00:00',
            ],
            [
                'period' => '2026-10', 'sales_bdt' => 15230, 'pool_bdt' => 0,
                'total_bdt' => 15230, 'status' => AuthorPayout::STATUS_PAID,
                'payout_trx_id' => 'DEMOSEED02', 'paid_at' => '2026-10-10 10:00:00',
            ],
            [
                'period' => '2026-11', 'sales_bdt' => 48640, 'pool_bdt' => 636,
                'total_bdt' => 49276, 'status' => AuthorPayout::STATUS_PENDING,
                'payout_trx_id' => null, 'paid_at' => null,
            ],
        ];

        foreach ($payouts as $row) {
            AuthorPayout::updateOrCreate(
                ['author_id' => $author->id, 'period' => $row['period']],
                array_merge($row, [
                    'method' => 'bkash',
                    'destination' => '01700000210',
                ])
            );
        }

        // ---- §7.16 pool settings keys. ----
        $settings = [
            'pool.share_pct' => '80',
            'pool.fee_rate_pct' => '2',
            'pool.count_min_minutes' => '10',
            'pool.count_min_percent' => '10',
            'pool.min_payout_bdt' => '500',
            'pool.payout_day' => '10',
            'pool.carry_bdt' => '0',
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
