<?php

namespace Tests\Feature;

use App\Models\Book;
use Database\Seeders\AuthorSeeder;
use Database\Seeders\BookSeeder;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([CategorySeeder::class, AuthorSeeder::class, BookSeeder::class]);
    }

    public function test_transliterated_latin_query_matches_gitanjali(): void
    {
        $this->get('/bn/search?q=gitanjali')
            ->assertOk()
            ->assertSee('Gitanjali')
            ->assertDontSee('The Prophet');
    }

    public function test_bengali_query_matches_gitanjali(): void
    {
        $this->get('/bn/search?q='.urlencode('গীতাঞ্জলি'))
            ->assertOk()
            ->assertSee('Gitanjali');
    }

    public function test_bengali_title_query_matches_devdas(): void
    {
        $this->get('/bn/search?q='.urlencode('দেবদাস'))
            ->assertOk()
            ->assertSee('দেবদাস');
    }

    public function test_author_name_query_matches(): void
    {
        $this->get('/bn/search?q=Tagore')
            ->assertOk()
            ->assertSee('Gitanjali')
            ->assertSee('শেষের কবিতা');
    }

    public function test_no_results_message_for_unknown_query(): void
    {
        $this->get('/bn/search?q=xyzqqqnotabook')
            ->assertOk()
            ->assertSee('xyzqqqnotabook');
    }

    public function test_livewire_search_matches_both_scripts(): void
    {
        Livewire::test(\App\Livewire\BookSearch::class)
            ->set('query', 'gitanjali')
            ->assertSee('Gitanjali');

        Livewire::test(\App\Livewire\BookSearch::class)
            ->set('query', 'গীতাঞ্জলি')
            ->assertSee('Gitanjali');
    }

    public function test_search_service_matches_both_scripts_directly(): void
    {
        $service = app(\App\Services\BookService::class);

        $latin = $service->search('gitanjali');
        $this->assertTrue($latin->contains(fn (Book $b) => $b->slug === 'gitanjali'));

        $bengali = $service->search('গীতাঞ্জলি');
        $this->assertTrue($bengali->contains(fn (Book $b) => $b->slug === 'gitanjali'));

        $this->assertTrue($service->search('xyzqqqnotabook')->isEmpty());
    }
}
