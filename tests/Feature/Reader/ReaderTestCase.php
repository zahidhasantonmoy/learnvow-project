<?php

namespace Tests\Feature\Reader;

use App\Models\Book;
use App\Models\User;
use App\Models\UserBook;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ReaderTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $reader;

    protected Book $prophet;

    protected Book $gitanjali;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([CategorySeeder::class, AuthorSeeder::class, BookSeeder::class]);

        $this->reader = User::factory()->create(['phone' => '01700000002']);
        $this->prophet = Book::where('slug', 'the-prophet')->firstOrFail();
        $this->gitanjali = Book::where('slug', 'gitanjali')->firstOrFail();

        // Entitlement for the paid book (Prophet, ৳96 sale).
        UserBook::create([
            'user_id' => $this->reader->id,
            'book_id' => $this->prophet->id,
            'source' => 'purchase',
        ]);
    }

    protected function otherReader(): User
    {
        // No fixed phone here: tests call this more than once per test and
        // the phone column is unique.
        return User::factory()->create();
    }
}
