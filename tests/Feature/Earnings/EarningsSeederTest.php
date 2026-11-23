<?php

namespace Tests\Feature\Earnings;

use App\Models\Author;
use App\Models\AuthorEarning;
use App\Models\AuthorPayout;
use App\Models\Book;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\AuthorEarningsSeeder;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 §8.1–§8.2 — AuthorEarningsSeeder: frozen demo opening balance,
 * payout history, and pool settings keys. Nothing from §8.4 is seeded
 * (no ৳322,500 model figures, no Tanvir/Nusrat rows — A18).
 */
class EarningsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_plants_frozen_demo_figures(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            CategorySeeder::class,
            AuthorSeeder::class,
            BookSeeder::class,
            DemoUserSeeder::class,
            AuthorEarningsSeeder::class,
        ]);

        $author = Author::where('slug', 'learnvow-commissioned-series')->firstOrFail();
        $this->assertSame(
            User::where('phone', '01700000003')->first()->id,
            (int) $author->user_id
        );

        // §8.1 — demo opening balance, type=adjustment, period 2026-11.
        $byBook = AuthorEarning::where('author_id', $author->id)
            ->where('type', 'adjustment')
            ->where('period', '2026-11')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->book->slug => $row->amount_bdt]);

        $this->assertSame(8000, $byBook['gitanjali']);
        $this->assertSame(5600, $byBook['prompt-engineering-basics']);
        $this->assertSame(35040, $byBook['the-prophet']);
        $this->assertSame(
            'demo opening balance (prototype frozen figures)',
            AuthorEarning::where('author_id', $author->id)->where('type', 'adjustment')->value('note')
        );

        $pool = AuthorEarning::where('author_id', $author->id)->where('type', 'pool')->first();
        $this->assertNotNull($pool);
        $this->assertSame(636, $pool->amount_bdt);
        $this->assertNull($pool->book_id);

        $this->assertSame(49276, AuthorEarning::where('author_id', $author->id)->sum('amount_bdt'));

        // §8.2 — payout history.
        $payouts = AuthorPayout::where('author_id', $author->id)->orderBy('period')->get();
        $this->assertCount(3, $payouts);
        $this->assertSame(11980, $payouts[0]->total_bdt);
        $this->assertSame('paid', $payouts[0]->status);
        $this->assertSame(15230, $payouts[1]->total_bdt);
        $this->assertSame('paid', $payouts[1]->status);
        $this->assertSame(49276, $payouts[2]->total_bdt);
        $this->assertSame('pending', $payouts[2]->status);
        $this->assertSame(48640, $payouts[2]->sales_bdt);
        $this->assertSame(636, $payouts[2]->pool_bdt);
        $this->assertSame('··210', $payouts[2]->maskedDestination());

        // §7.16 pool settings keys.
        $this->assertSame('80', Setting::where('key', 'pool.share_pct')->value('value'));
        $this->assertSame('2', Setting::where('key', 'pool.fee_rate_pct')->value('value'));
        $this->assertSame('10', Setting::where('key', 'pool.count_min_minutes')->value('value'));
        $this->assertSame('10', Setting::where('key', 'pool.count_min_percent')->value('value'));
        $this->assertSame('500', Setting::where('key', 'pool.min_payout_bdt')->value('value'));
        $this->assertSame('10', Setting::where('key', 'pool.payout_day')->value('value'));
        $this->assertSame('0', Setting::where('key', 'pool.carry_bdt')->value('value'));

        // §8.4 — no model figures anywhere.
        $this->assertFalse(Book::where('title', 'like', '%322500%')->exists());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed([
            RolePermissionSeeder::class,
            CategorySeeder::class,
            AuthorSeeder::class,
            BookSeeder::class,
            DemoUserSeeder::class,
            AuthorEarningsSeeder::class,
            AuthorEarningsSeeder::class,
        ]);

        $author = Author::where('slug', 'learnvow-commissioned-series')->firstOrFail();
        $this->assertSame(4, AuthorEarning::where('author_id', $author->id)->count());
        $this->assertSame(3, AuthorPayout::where('author_id', $author->id)->count());
    }
}
