<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\Collection;
use Livewire\Component;

/** Manage shelves: create/rename/delete + add/remove/reorder books (§4.2). */
class CollectionManager extends Component
{
    public ?int $selectedId = null;

    public string $name = '';

    public string $description = '';

    public bool $isPublic = false;

    public ?int $editingId = null;

    public string $editName = '';

    public function mount(?int $collectionId = null): void
    {
        $this->selectedId = $collectionId;
    }

    public function createCollection(): void
    {
        $user = auth()->user();
        $this->validate([
            'name' => ['required', 'string', 'max:100', 'unique:collections,name,NULL,id,user_id,'.$user->id],
        ]);

        if (Collection::where('user_id', $user->id)->count() >= Collection::MAX_PER_USER) {
            $this->addError('name', __('books.collections_limit_reached', ['max' => Collection::MAX_PER_USER]));
            return;
        }

        $collection = Collection::create([
            'user_id' => $user->id,
            'name' => $this->name,
            'description' => $this->description ?: null,
            'is_public' => $this->isPublic,
        ]);

        $this->reset(['name', 'description', 'isPublic']);
        $this->selectedId = $collection->id;
        session()->flash('status', __('books.collections_created'));
    }

    public function startEdit(int $id): void
    {
        $collection = $this->ownCollection($id);
        $this->editingId = $id;
        $this->editName = $collection->name;
    }

    public function updateCollection(): void
    {
        $collection = $this->ownCollection($this->editingId);
        $this->validate(['editName' => ['required', 'string', 'max:100']]);
        $collection->update(['name' => $this->editName]);
        $this->editingId = null;
        session()->flash('status', __('books.collections_updated'));
    }

    public function deleteCollection(int $id): void
    {
        $this->ownCollection($id)->delete();
        if ($this->selectedId === $id) {
            $this->selectedId = null;
        }
        session()->flash('status', __('books.collections_deleted'));
    }

    public function selectCollection(int $id): void
    {
        $this->ownCollection($id);
        $this->selectedId = $id;
    }

    public function addBook(int $bookId): void
    {
        $collection = $this->ownCollection($this->selectedId);
        $book = Book::findOrFail($bookId);

        if ($collection->items()->count() >= Collection::MAX_BOOKS) {
            $this->addError('name', __('books.collections_books_limit_reached', ['max' => Collection::MAX_BOOKS]));
            return;
        }

        $collection->items()->firstOrCreate(
            ['book_id' => $book->id],
            ['sort_order' => (int) ($collection->items()->max('sort_order') ?? 0) + 1, 'added_at' => now()]
        );
    }

    public function removeBook(int $bookId): void
    {
        $this->ownCollection($this->selectedId)->items()->where('book_id', $bookId)->delete();
    }

    public function moveItem(int $itemId, string $direction): void
    {
        $collection = $this->ownCollection($this->selectedId);
        $item = $collection->items()->findOrFail($itemId);
        $swap = $direction === 'up'
            ? $collection->items()->where('sort_order', '<', $item->sort_order)->orderByDesc('sort_order')->first()
            : $collection->items()->where('sort_order', '>', $item->sort_order)->orderBy('sort_order')->first();

        if ($swap === null) {
            return;
        }

        [$item->sort_order, $swap->sort_order] = [$swap->sort_order, $item->sort_order];
        $item->save();
        $swap->save();
    }

    protected function ownCollection(?int $id): Collection
    {
        return Collection::where('user_id', auth()->id())->findOrFail($id);
    }

    public function render()
    {
        $collections = Collection::where('user_id', auth()->id())
            ->withCount('items')->orderBy('sort_order')->orderBy('name')->get();
        $selected = $this->selectedId
            ? Collection::where('user_id', auth()->id())->with(['items.book.author'])->find($this->selectedId)
            : null;

        return view('livewire.collection-manager', compact('collections', 'selected'));
    }
}
