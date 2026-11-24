<?php

namespace Tests\Feature\Books;

use App\Models\Author;
use App\Models\Book;
use App\Models\Follow;
use App\Models\User;
use App\Models\UserBook;
use App\Models\Wishlist;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BookCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, AuthorSeeder::class, BookSeeder::class]);

        // TEST-ONLY STUB: the coordinator-owned layout calls route('profile.show')
        // for logged-in users, but profile routes are wired by the coordinator
        // only after Builders A and B finish. Stub it here so authenticated
        // page renders can be verified; remove once the real route exists.
        if (! Route::has('profile.show')) {
            Route::get('/{locale}/profile', fn () => 'profile stub')
                ->where(['locale' => 'bn|en'])->name('profile.show');
            Route::getRoutes()->refreshNameLookups();
        }
    }

    protected function reader(array $attrs = []): User
    {
        return User::factory()->create(array_merge(
            ['phone' => '01700000002'],
            $attrs
        ));
    }

    public function test_book_list_renders_all_eight_seeded_books_with_exact_prices(): void
    {
        $this->assertSame(8, Book::count());

        $response = $this->get('/bn/books');
        $response->assertOk();
        $response->assertSee('The Prophet');
        $response->assertSee('Gitanjali');
        $response->assertSee('পদ্মা নদীর মাঝি');
        $response->assertSee('৳96');
        $response->assertSee('৳199');
        $response->assertSee('৳150');
        $response->assertSee('৳99');
    }

    public function test_book_detail_shows_prophet_at_96_with_list_price_120_and_book_json_ld(): void
    {
        $response = $this->get('/bn/books/the-prophet');
        $response->assertOk();
        $response->assertSee('৳96');
        $response->assertSee('৳120');
        $response->assertSee('"@type":"Book"', false);
        $response->assertSee('Kahlil Gibran');
        // No fake ISBN and no AggregateRating (no real reviews exist yet).
        $response->assertDontSee('"isbn"');
        $response->assertDontSee('aggregateRating');
    }

    public function test_book_detail_shows_buy_button_when_not_owned(): void
    {
        // UI-01: the detail page showed the price but no path to checkout.
        $response = $this->get('/bn/books/the-prophet');
        $response->assertOk();
        $response->assertSee('/bn/checkout/the-prophet', false);
        $response->assertSee('এখনই কিনুন', false);
    }

    public function test_book_detail_shows_read_button_when_owned(): void
    {
        // UI-02: an owned book must offer a direct path into the reader.
        $reader = $this->reader();
        UserBook::create([
            'user_id' => $reader->id,
            'book_id' => Book::where('slug', 'the-prophet')->firstOrFail()->id,
            'source' => 'purchase',
        ]);

        $response = $this->actingAs($reader)->get('/bn/books/the-prophet');
        $response->assertOk();
        $response->assertSee('/bn/reader/the-prophet', false);
        $response->assertSee('এখনই পড়ুন', false);
        $response->assertDontSee('/bn/checkout/the-prophet', false);
    }

    public function test_library_cards_link_into_the_reader(): void
    {
        // UI-02: library cards linked only to the detail page.
        $reader = $this->reader();
        UserBook::create([
            'user_id' => $reader->id,
            'book_id' => Book::where('slug', 'the-prophet')->firstOrFail()->id,
            'source' => 'purchase',
        ]);

        $response = $this->actingAs($reader)->get('/bn/library');
        $response->assertOk();
        $response->assertSee('/bn/reader/the-prophet', false);
    }

    public function test_profile_links_the_orphaned_phase4_screens(): void
    {
        // UI-03: for-you, goals, badges, preorders, request-book, taste,
        // devices, community and clubs had no inbound link anywhere.
        $reader = $this->reader();

        $response = $this->actingAs($reader)->get('/bn/profile');
        $response->assertOk();
        foreach ([
            '/bn/for-you', '/bn/reading-goals', '/bn/badges', '/bn/preorders',
            '/bn/request-book', '/bn/profile/taste', '/bn/profile/devices',
            '/bn/community', '/bn/clubs',
        ] as $url) {
            $response->assertSee($url, false, "Profile page does not link {$url}");
        }
    }

    public function test_free_books_page_lists_only_free_books_at_zero(): void
    {
        $response = $this->get('/bn/books/free');
        $response->assertOk();
        $response->assertSee('Gitanjali');
        $response->assertSee('দেবদাস');
        $response->assertSee('শেষের কবিতা');
        $response->assertDontSee('The Prophet');
        $response->assertDontSee('৳96');
    }

    public function test_category_page_filters_books(): void
    {
        $response = $this->get('/bn/categories/novel');
        $response->assertOk();
        $response->assertSee('পদ্মা নদীর মাঝি');
        $response->assertSee('Pride and Prejudice');
        $response->assertDontSee('The Prophet');
        $response->assertDontSee('Meditations');
    }

    public function test_author_page_shows_books_and_follow_toggle_works(): void
    {
        $user = $this->reader();
        $response = $this->get('/bn/authors/rabindranath-tagore');
        $response->assertOk();
        $response->assertSee('Gitanjali');
        $response->assertSee('শেষের কবিতা');
        $response->assertDontSee('The Prophet');

        $author = Author::where('slug', 'rabindranath-tagore')->firstOrFail();

        $this->actingAs($user)->post('/bn/authors/rabindranath-tagore/follow')
            ->assertRedirect();
        $this->assertTrue(Follow::where('user_id', $user->id)->where('author_id', $author->id)->exists());

        $this->actingAs($user)->post('/bn/authors/rabindranath-tagore/follow')
            ->assertRedirect();
        $this->assertFalse(Follow::where('user_id', $user->id)->where('author_id', $author->id)->exists());
    }

    public function test_wishlist_toggle_adds_and_removes(): void
    {
        $user = $this->reader();
        $book = Book::where('slug', 'the-prophet')->firstOrFail();

        $this->actingAs($user)->post('/bn/books/the-prophet/wishlist')->assertRedirect();
        $this->assertTrue(Wishlist::where('user_id', $user->id)->where('book_id', $book->id)->exists());

        $this->actingAs($user)->get('/bn/wishlist')->assertOk()->assertSee('The Prophet');

        $this->actingAs($user)->post('/bn/books/the-prophet/wishlist')->assertRedirect();
        $this->assertFalse(Wishlist::where('user_id', $user->id)->where('book_id', $book->id)->exists());
    }

    public function test_library_shows_only_entitled_books(): void
    {
        $user = $this->reader();
        $free = Book::where('slug', 'gitanjali')->firstOrFail();
        UserBook::create(['user_id' => $user->id, 'book_id' => $free->id, 'source' => 'free']);

        $response = $this->actingAs($user)->get('/bn/library');
        $response->assertOk();
        $response->assertSee('Gitanjali');
        $response->assertDontSee('The Prophet');
    }

    public function test_sitemap_lists_books_authors_categories_and_blog(): void
    {
        $response = $this->get('/sitemap.xml');
        $response->assertOk();
        $response->assertSee('/bn/books/the-prophet', false);
        $response->assertSee('/en/books/gitanjali', false);
        $response->assertSee('/bn/authors/rabindranath-tagore', false);
        $response->assertSee('/bn/categories/novel', false);
    }

    public function test_robots_allows_ai_answer_crawlers_and_blocks_training_crawlers(): void
    {
        $response = $this->get('/robots.txt');
        $response->assertOk();
        $response->assertSee('OAI-SearchBot', false);
        $response->assertSee('PerplexityBot', false);
        $response->assertSee('GPTBot', false);
        $response->assertSee('Disallow: /admin/', false);
        $response->assertSee('Disallow: /reader/', false);
    }
}
