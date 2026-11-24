<?php

namespace Tests\Feature\Reader;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\TasteProfile;
use App\Services\ReaderService;
use App\Services\RecommendationService;

class ForYouTest extends ReaderTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BadgeSeeder::class);
    }

    protected function makeBook(string $slug, string $categorySlug, string $authorSlug, array $extra = []): Book
    {
        return Book::create(array_merge([
            'author_id' => Author::where('slug', $authorSlug)->firstOrFail()->id,
            'category_id' => Category::where('slug', $categorySlug)->firstOrFail()->id,
            'title' => $slug,
            'description' => 'Test book.',
            'language' => 'en',
            'price_bdt' => 0,
            'status' => 'published',
        ], $extra, ['slug' => $slug]));
    }

    protected function finish(Book $book): void
    {
        app(ReaderService::class)->saveProgress($this->reader, $book, [
            'percent' => 100, 'total_minutes' => 30, 'last_read_at' => now()->toISOString(),
        ]);
    }

    public function test_for_you_uses_finished_category_with_reason(): void
    {
        $newPoetry = $this->makeBook('new-poems', 'poetry', 'rabindranath-tagore', ['published_at' => now()->subDays(2)]);
        $this->finish($this->gitanjali);

        $results = app(RecommendationService::class)->forUser($this->reader);
        $slugs = collect($results)->map(fn ($r) => $r['book']->slug)->all();

        $this->assertContains('new-poems', $slugs);
        $this->assertNotContains('gitanjali', $slugs); // already read: never recommended
        $match = collect($results)->firstWhere(fn ($r) => $r['book']->slug === 'new-poems');
        $this->assertStringContainsString('Poetry', $match['reason']);
    }

    public function test_for_you_excludes_own_books(): void
    {
        $author = Author::create([
            'user_id' => $this->reader->id, 'name' => 'My Pen', 'slug' => 'my-pen', 'status' => 'approved',
        ]);
        $own = $this->makeBook('my-own-book', 'novel', 'my-pen');

        $results = app(RecommendationService::class)->forUser($this->reader);
        $slugs = collect($results)->map(fn ($r) => $r['book']->slug)->all();
        $this->assertNotContains('my-own-book', $slugs);
    }

    public function test_for_you_favourite_author_rule(): void
    {
        $tagore = Author::where('slug', 'rabindranath-tagore')->firstOrFail();
        TasteProfile::create([
            'user_id' => $this->reader->id, 'authors' => [$tagore->id], 'genres' => [], 'languages' => ['bn'],
        ]);
        $newTagore = $this->makeBook('tagore-new', 'novel', 'rabindranath-tagore', ['published_at' => now()->subDays(1)]);

        $results = app(RecommendationService::class)->forUser($this->reader);
        $match = collect($results)->firstWhere(fn ($r) => $r['book']->slug === 'tagore-new');
        $this->assertNotNull($match);
        $this->assertStringContainsString('Rabindranath Tagore', $match['reason']);
    }

    public function test_for_you_fallback_is_most_sold_for_new_reader(): void
    {
        // Seeded books all carry published_at=now(), so the "newly
        // published" rule fires first for a new reader; the popular
        // fallback (Prophet, 365 sales) still fills the list.
        $results = app(RecommendationService::class)->forUser($this->reader);
        $this->assertNotEmpty($results);
        $slugs = collect($results)->map(fn ($r) => $r['book']->slug)->all();
        $this->assertContains('the-prophet', $slugs);
        $this->assertContains('gitanjali', $slugs);
        foreach ($results as $result) {
            $this->assertNotEmpty($result['reason']);
        }
    }

    public function test_for_you_page_renders(): void
    {
        $this->actingAs($this->reader);
        $this->get('/bn/for-you')->assertOk()->assertSee('The Prophet');
    }

    public function test_territory_rule_blocks_premium_only(): void
    {
        // Prophet's seeded territories default to ["BD"].
        $this->prophet->rights_territories = ['BD'];
        $this->prophet->save();

        $books = app(\App\Services\BookService::class);

        // Empty country never blocks.
        $this->assertTrue($books->territoryAllows($this->reader, $this->prophet));

        // US user is outside BD rights — Premium reading blocked.
        $usReader = $this->otherReader();
        $usReader->country = 'US';
        $usReader->save();
        $this->assertFalse($books->territoryAllows($usReader, $this->prophet));
        $this->assertFalse($books->canRead($usReader, $this->prophet));

        // ...but a bought book is exempt from the territory rule.
        \App\Models\UserBook::create([
            'user_id' => $usReader->id, 'book_id' => $this->prophet->id, 'source' => 'purchase',
        ]);
        $this->assertTrue($books->canRead($usReader, $this->prophet));
    }
}
