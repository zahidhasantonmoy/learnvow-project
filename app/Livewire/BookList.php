<?php

namespace App\Livewire;

use App\Services\BookService;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BookList extends Component
{
    use WithPagination;

    #[Url(as: 'category')]
    public string $categorySlug = '';

    #[Url(as: 'price')]
    public string $price = '';

    #[Url(as: 'language')]
    public string $language = '';

    #[Url(as: 'sort')]
    public string $sort = 'newest';

    public function mount(array $filters = []): void
    {
        $this->categorySlug = (string) ($filters['category_slug'] ?? '');
        $this->price = (string) ($filters['price'] ?? '');
        $this->language = (string) ($filters['language'] ?? '');
        $this->sort = (string) ($filters['sort'] ?? 'newest');
    }

    #[On('filters-updated')]
    public function applyFilters(array $filters): void
    {
        $this->categorySlug = (string) ($filters['category_slug'] ?? '');
        $this->price = (string) ($filters['price'] ?? '');
        $this->language = (string) ($filters['language'] ?? '');
        $this->sort = (string) ($filters['sort'] ?? 'newest');
        $this->resetPage();
    }

    public function render(BookService $books)
    {
        return view('livewire.book-list', [
            'books' => $books->list(array_filter([
                'category_slug' => $this->categorySlug,
                'price' => $this->price,
                'language' => $this->language,
                'sort' => $this->sort,
            ])),
        ]);
    }
}
