<?php

namespace App\Livewire;

use App\Models\Category;
use Livewire\Component;

class BookFilters extends Component
{
    public string $categorySlug = '';
    public string $price = '';
    public string $language = '';
    public string $sort = 'newest';

    public function mount(array $filters = []): void
    {
        $this->categorySlug = (string) ($filters['category_slug'] ?? '');
        $this->price = (string) ($filters['price'] ?? '');
        $this->language = (string) ($filters['language'] ?? '');
        $this->sort = (string) ($filters['sort'] ?? 'newest');
    }

    public function updated(): void
    {
        $this->dispatch('filters-updated', filters: $this->currentFilters());
    }

    public function currentFilters(): array
    {
        return array_filter([
            'category_slug' => $this->categorySlug,
            'price' => $this->price,
            'language' => $this->language,
            'sort' => $this->sort,
        ]);
    }

    public function render()
    {
        return view('livewire.book-filters', [
            'categories' => Category::active()->orderBy('sort_order')->get(),
        ]);
    }
}
