<?php

namespace App\Livewire;

use App\Services\BookService;
use Livewire\Component;

class BookSearch extends Component
{
    public string $query = '';

    public function mount(string $initialQuery = ''): void
    {
        $this->query = $initialQuery;
    }

    public function render(BookService $books)
    {
        $results = mb_strlen(trim($this->query)) >= 2
            ? $books->search($this->query)
            : collect();

        return view('livewire.book-search', [
            'results' => $results,
            'searched' => mb_strlen(trim($this->query)) >= 2,
        ]);
    }
}
