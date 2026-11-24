<?php

namespace App\Livewire;

use App\Models\VocabularyWord;
use Livewire\Component;
use Livewire\WithPagination;

/** Vocabulary list: search, add, delete (§4.11). */
class VocabularyManager extends Component
{
    use WithPagination;

    public string $search = '';

    public function mount(string $search = ''): void
    {
        $this->search = $search;
    }

    public string $word = '';

    public string $meaningEn = '';

    public string $meaningBn = '';

    public string $example = '';

    public function addWord(): void
    {
        $this->validate([
            'word' => ['required', 'string', 'max:100'],
            'meaningEn' => ['nullable', 'string', 'max:2000'],
            'meaningBn' => ['nullable', 'string', 'max:2000'],
            'example' => ['nullable', 'string', 'max:2000'],
        ]);

        VocabularyWord::firstOrCreate(
            ['user_id' => auth()->id(), 'word' => mb_strtolower(trim($this->word))],
            [
                'meaning_en' => $this->meaningEn ?: null,
                'meaning_bn' => $this->meaningBn ?: null,
                'example' => $this->example ?: null,
            ]
        );

        $this->reset(['word', 'meaningEn', 'meaningBn', 'example']);
        session()->flash('status', __('reader.vocabulary_added'));
    }

    public function deleteWord(int $id): void
    {
        VocabularyWord::where('user_id', auth()->id())->findOrFail($id)->delete();
        session()->flash('status', __('reader.vocabulary_deleted'));
    }

    public function render()
    {
        $words = VocabularyWord::where('user_id', auth()->id())
            ->when($this->search !== '', fn ($q) => $q->where('word', 'like', '%'.mb_strtolower($this->search).'%'))
            ->with('book')->latest('created_at')->paginate(20);

        return view('livewire.vocabulary-manager', compact('words'));
    }
}
