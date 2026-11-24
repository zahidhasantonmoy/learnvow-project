<?php

namespace Tests\Feature\Reader;

use App\Models\Badge;
use App\Models\Book;
use App\Models\ReadingGoal;
use App\Models\ReadingMinute;
use App\Models\ReadingProgress;
use App\Models\UserBadge;
use App\Services\BadgeService;
use App\Services\ReaderService;

class GoalsBadgesTest extends ReaderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BadgeSeeder::class);
    }

    public function test_badge_seeder_creates_eight_definitions_only(): void
    {
        $this->assertEquals(8, Badge::count());
        $this->assertEquals(0, UserBadge::count());
        $this->assertNotNull(Badge::where('slug', 'first_book')->first());
        $this->assertNotNull(Badge::where('slug', 'goal_completed')->first());
    }

    public function test_first_book_badge_awarded_on_first_finish(): void
    {
        app(ReaderService::class)->saveProgress($this->reader, $this->gitanjali, [
            'percent' => 100, 'total_minutes' => 30, 'last_read_at' => now()->toISOString(),
        ]);

        $this->assertDatabaseHas('user_badges', [
            'user_id' => $this->reader->id,
            'badge_id' => Badge::where('slug', 'first_book')->firstOrFail()->id,
        ]);

        // Awarded once only — finishing a second book adds no duplicate.
        app(ReaderService::class)->saveProgress($this->reader, $this->prophet, [
            'percent' => 100, 'total_minutes' => 30, 'last_read_at' => now()->toISOString(),
        ]);
        $this->assertEquals(1, UserBadge::where('user_id', $this->reader->id)
            ->where('badge_id', Badge::where('slug', 'first_book')->first()->id)->count());
    }

    public function test_night_owl_badge_for_night_finish(): void
    {
        app(ReaderService::class)->saveProgress($this->reader, $this->gitanjali, [
            'percent' => 100, 'total_minutes' => 30,
            'last_read_at' => now()->setTime(2, 15)->toISOString(),
        ]);

        $this->assertTrue(app(BadgeService::class)->hasBadge($this->reader, 'night_owl'));
    }

    public function test_streak_badge_after_seven_consecutive_days(): void
    {
        // One progress row per user+book, so 7 consecutive days need
        // 7 different books.
        for ($i = 0; $i < 7; $i++) {
            $book = \App\Models\Book::create([
                'author_id' => $this->gitanjali->author_id,
                'category_id' => $this->gitanjali->category_id,
                'title' => 'Streak book '.$i,
                'slug' => 'streak-book-'.$i,
                'description' => 'Test book.',
                'language' => 'en',
                'price_bdt' => 0,
                'status' => 'published',
            ]);
            ReadingProgress::create([
                'user_id' => $this->reader->id,
                'book_id' => $book->id,
                'percent' => 10,
                'total_minutes' => 5,
                'last_read_at' => now()->subDays($i),
            ]);
        }

        $this->assertEquals(7, app(BadgeService::class)->readingStreakDays($this->reader));

        app(BadgeService::class)->checkAndAward($this->reader, 'progress_saved');
        $this->assertTrue(app(BadgeService::class)->hasBadge($this->reader, 'streak_7'));
        $this->assertFalse(app(BadgeService::class)->hasBadge($this->reader, 'streak_30'));
    }

    public function test_goal_progress_books_and_minutes(): void
    {
        $goal = ReadingGoal::create([
            'user_id' => $this->reader->id, 'year' => (int) now()->year,
            'goal_type' => 'books', 'target' => 2,
        ]);

        app(ReaderService::class)->saveProgress($this->reader, $this->gitanjali, [
            'percent' => 100, 'total_minutes' => 30, 'last_read_at' => now()->toISOString(),
        ]);

        $this->assertEquals(1, $goal->fresh()->progress());
        $this->assertEquals(50, $goal->fresh()->percent());
        $this->assertFalse($goal->fresh()->isComplete());

        app(ReaderService::class)->saveProgress($this->reader, $this->prophet, [
            'percent' => 100, 'total_minutes' => 30, 'last_read_at' => now()->toISOString(),
        ]);
        $this->assertTrue($goal->fresh()->isComplete());

        $minutesGoal = ReadingGoal::create([
            'user_id' => $this->reader->id, 'year' => (int) now()->year,
            'goal_type' => 'minutes', 'target' => 60,
        ]);
        // Devdas is untouched by saveProgress above, so no ledger row
        // exists for it yet (saveProgress upserts one per finished book).
        $devdas = Book::where('slug', 'devdas')->firstOrFail();
        ReadingMinute::create([
            'user_id' => $this->reader->id, 'book_id' => $devdas->id,
            'author_id' => $devdas->author_id, 'period' => now()->format('Y-m'), 'minutes' => 45,
        ]);
        // Minutes goal sums the whole year's ledger: 30 (Gitanjali) +
        // 30 (Prophet) recorded by saveProgress above, plus 45 here.
        $this->assertEquals(105, $minutesGoal->fresh()->progress());
        $this->assertEquals(100, $minutesGoal->fresh()->percent());
        $this->assertTrue($minutesGoal->fresh()->isComplete());
    }

    public function test_goal_crud_and_validation_ranges(): void
    {
        $this->actingAs($this->reader);

        // Books goal above 365 is rejected.
        $this->post('/bn/reading-goals', ['year' => now()->year, 'goal_type' => 'books', 'target' => 400])
            ->assertSessionHasErrors('target');

        // Minutes goal below 60 is rejected.
        $this->post('/bn/reading-goals', ['year' => now()->year, 'goal_type' => 'minutes', 'target' => 30])
            ->assertSessionHasErrors('target');

        $this->post('/bn/reading-goals', ['year' => now()->year, 'goal_type' => 'books', 'target' => 24])
            ->assertRedirect();
        $goal = ReadingGoal::where('user_id', $this->reader->id)->firstOrFail();
        $this->assertEquals(24, $goal->target);

        // Same year+type updates the same row (unique).
        $this->post('/bn/reading-goals', ['year' => now()->year, 'goal_type' => 'books', 'target' => 30])
            ->assertRedirect();
        $this->assertEquals(1, ReadingGoal::where('user_id', $this->reader->id)->count());
        $this->assertEquals(30, $goal->fresh()->target);

        $this->get('/bn/reading-goals')->assertOk();
        $this->delete("/bn/reading-goals/{$goal->id}")->assertRedirect();
        $this->assertDatabaseMissing('reading_goals', ['id' => $goal->id]);
    }

    public function test_goal_completed_badge_via_goal_tracker(): void
    {
        ReadingGoal::create([
            'user_id' => $this->reader->id, 'year' => (int) now()->year,
            'goal_type' => 'books', 'target' => 1,
        ]);
        app(ReaderService::class)->saveProgress($this->reader, $this->gitanjali, [
            'percent' => 100, 'total_minutes' => 30, 'last_read_at' => now()->toISOString(),
        ]);

        $this->actingAs($this->reader);
        \Livewire\Livewire::test(\App\Livewire\GoalTracker::class)
            ->call('checkCompletion')
            ->assertHasNoErrors();

        $this->assertTrue(app(BadgeService::class)->hasBadge($this->reader, 'goal_completed'));
    }

    public function test_badges_page_shows_english_descriptions_in_english(): void
    {
        // QA minor: EN page showed Bengali descriptions (names were
        // translated, descriptions were not). description_en is seeded.
        $this->assertSame(
            'You get this badge when you finish your first book.',
            Badge::where('slug', 'first_book')->firstOrFail()->description_en
        );
        $this->assertSame(
            'প্রথম বইটি শেষ করলে এই ব্যাজ পাওয়া যায়।',
            Badge::where('slug', 'first_book')->firstOrFail()->description
        );

        $this->actingAs($this->reader);
        $this->get('/en/badges')
            ->assertOk()
            ->assertSee('You get this badge when you finish your first book.')
            ->assertDontSee('প্রথম বইটি শেষ করলে এই ব্যাজ পাওয়া যায়।');
        $this->get('/bn/badges')
            ->assertOk()
            ->assertSee('প্রথম বইটি শেষ করলে এই ব্যাজ পাওয়া যায়।');
    }

    public function test_badges_page_shows_earned_and_unearned(): void
    {
        app(ReaderService::class)->saveProgress($this->reader, $this->gitanjali, [
            'percent' => 100, 'total_minutes' => 30, 'last_read_at' => now()->toISOString(),
        ]);

        $this->actingAs($this->reader);
        $this->get('/bn/badges')->assertOk()->assertSee('প্রথম বই');
    }
}
