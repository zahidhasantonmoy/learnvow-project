<?php

namespace App\Livewire;

use App\Http\Controllers\PreorderController;
use App\Models\Book;
use App\Models\Preorder;
use Livewire\Component;

/** Pre-order reserve/cancel button (§4.8). Reservation only — no money. */
class PreorderButton extends Component
{
    public Book $book;

    public ?Preorder $preorder = null;

    public function mount(Book $book): void
    {
        $this->book = $book;
        $this->refreshPreorder();
    }

    public function reserve(): void
    {
        $user = auth()->user();
        if (! app(PreorderController::class)->isPreorderable($this->book)) {
            $this->addError('preorder', __('books.preorder_not_available'));
            return;
        }

        $this->preorder = Preorder::firstOrCreate(
            ['user_id' => $user->id, 'book_id' => $this->book->id, 'status' => 'reserved'],
            []
        );
        // firstOrCreate with a status in the key: an existing cancelled
        // row must not block a fresh reservation.
        if ($this->preorder->status === 'cancelled') {
            $this->preorder = Preorder::create([
                'user_id' => $user->id, 'book_id' => $this->book->id, 'status' => 'reserved',
            ]);
        }
        session()->flash('status', __('books.preorder_reserved'));
    }

    public function cancel(): void
    {
        if ($this->preorder !== null && $this->preorder->user_id === auth()->id()) {
            $this->preorder->status = 'cancelled';
            $this->preorder->save();
            $this->preorder = null;
            session()->flash('status', __('books.preorder_cancelled'));
        }
    }

    protected function refreshPreorder(): void
    {
        $this->preorder = Preorder::where('user_id', auth()->id())
            ->where('book_id', $this->book->id)
            ->whereIn('status', Preorder::ACTIVE_STATUSES)
            ->first();
    }

    public function render()
    {
        return view('livewire.preorder-button');
    }
}
