<?php

namespace Tests\Feature\Print;

use App\Exceptions\AiNotConfiguredException;
use App\Models\Book;
use App\Models\User;
use App\Services\AiService;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 spec §7 — AiService is a seam only: disabled by default,
 * every method throws AiNotConfiguredException, no external calls,
 * no API keys anywhere.
 */
class AiServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([
            RolePermissionSeeder::class, CategorySeeder::class,
            AuthorSeeder::class, BookSeeder::class,
        ]);
    }

    public function test_disabled_by_default_and_methods_throw(): void
    {
        $ai = app(AiService::class);

        $this->assertFalse($ai->enabled());
        $this->assertNull($ai->provider());

        $book = Book::where('slug', 'the-prophet')->firstOrFail();
        $user = User::factory()->create(['phone' => '01700000002']);

        $this->expectException(AiNotConfiguredException::class);
        $ai->summarizeBook($book);
    }

    public function test_recommend_and_narration_also_throw(): void
    {
        $ai = app(AiService::class);
        $book = Book::where('slug', 'the-prophet')->firstOrFail();
        $user = User::factory()->create(['phone' => '01700000002']);

        try {
            $ai->recommendFor($user);
            $this->fail('recommendFor should throw AiNotConfiguredException.');
        } catch (AiNotConfiguredException) {
            $this->assertTrue(true);
        }

        $this->expectException(AiNotConfiguredException::class);
        $ai->narrationScript($book);
    }
}
