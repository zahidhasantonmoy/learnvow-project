<?php

namespace Tests\Feature\Author;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;
use ZipArchive;

abstract class AuthorTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $reader;

    protected User $authorUser;

    protected Author $author;

    protected Book $prophet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([
            RolePermissionSeeder::class, CategorySeeder::class,
            AuthorSeeder::class, BookSeeder::class,
        ]);

        $this->reader = User::factory()->create(['phone' => '01700000002']);
        $this->reader->assignRole('reader');

        // Approved author (demo phone 01700000003) linked to the
        // Commissioned Series author row.
        $this->authorUser = User::factory()->create(['phone' => '01700000003', 'name' => 'Demo Author']);
        $this->authorUser->assignRole('reader');
        $this->authorUser->assignRole('author');

        $this->author = Author::where('slug', 'learnvow-commissioned-series')->firstOrFail();
        $this->author->update(['user_id' => $this->authorUser->id, 'status' => 'approved']);

        $this->prophet = Book::where('slug', 'the-prophet')->firstOrFail();
    }

    protected function actingAuthor(): static
    {
        $this->actingAs($this->authorUser);

        return $this;
    }

    protected function actingReader(): static
    {
        $this->actingAs($this->reader);

        return $this;
    }

    /** A real, valid EPUB: ZIP with mimetype + META-INF/container.xml. */
    protected function validEpub(string $name = 'book.epub'): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.uniqid('epub_', true).'.epub';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container version="1.0"><rootfiles><rootfile full-path="content.opf" media-type="application/oebps-package+xml"/></rootfiles></container>');
        $zip->addFromString('content.opf', '<package version="3.0"></package>');
        $zip->close();

        $bytes = file_get_contents($path);
        unlink($path);

        // Fake file carrying the real ZIP bytes (Livewire test uploads
        // need a fake UploadedFile; a plain `new UploadedFile` breaks
        // Livewire's test file handling).
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    /** A broken EPUB: a ZIP without the required entries. */
    protected function brokenEpub(): UploadedFile
    {
        $path = sys_get_temp_dir().'/'.uniqid('bad_', true).'.epub';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('random.txt', 'not an epub');
        $zip->close();

        $bytes = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('broken.epub', $bytes);
    }

    protected function publishData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Test Book Title',
            'title_en' => 'Test Book Title',
            'description' => 'A test book description.',
            'language' => 'en',
            'category_id' => Category::first()->id,
            'price_bdt' => 150,
            'list_price_bdt' => 200,
            'sample_percent' => 10,
        ], $overrides);
    }
}
