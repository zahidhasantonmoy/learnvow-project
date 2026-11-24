<?php

namespace App\Livewire;

use App\Models\Author;
use App\Models\Category;
use App\Models\TasteProfile;
use Livewire\Component;

/** Taste profile form (§4.10): genres, authors, languages, reading time. */
class TasteProfileForm extends Component
{
    public array $genres = [];

    public array $authors = [];

    public array $languages = [];

    public ?string $readingTime = null;

    public string $authorSearch = '';

    public function mount(): void
    {
        $profile = TasteProfile::where('user_id', auth()->id())->first();
        if ($profile !== null) {
            $this->genres = $profile->genreIds();
            $this->authors = $profile->authorIds();
            $this->languages = (array) ($profile->languages ?? []);
            $this->readingTime = $profile->reading_time;
        }
    }

    public function toggleAuthor(int $authorId): void
    {
        if (in_array($authorId, $this->authors, true)) {
            $this->authors = array_values(array_diff($this->authors, [$authorId]));
        } else {
            $this->authors[] = $authorId;
        }
    }

    public function save(): void
    {
        $this->validate([
            'genres' => ['array'],
            'genres.*' => ['integer', 'exists:categories,id'],
            'authors' => ['array'],
            'authors.*' => ['integer', 'exists:authors,id'],
            'languages' => ['array'],
            'languages.*' => ['in:bn,en'],
            'readingTime' => ['nullable', 'in:morning,afternoon,evening,night'],
        ]);

        TasteProfile::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'genres' => array_values($this->genres),
                'authors' => array_values($this->authors),
                'languages' => array_values($this->languages),
                'reading_time' => $this->readingTime,
            ]
        );

        session()->flash('status', __('books.taste_saved'));
    }

    public function render()
    {
        return view('livewire.taste-profile-form', [
            'categories' => Category::orderBy('sort_order')->get(),
            'foundAuthors' => $this->authorSearch !== ''
                ? Author::where('name', 'like', '%'.$this->authorSearch.'%')->orderBy('name')->limit(10)->get()
                : collect(),
            'chosenAuthors' => $this->authors !== []
                ? Author::whereIn('id', $this->authors)->orderBy('name')->get()
                : collect(),
        ]);
    }
}
