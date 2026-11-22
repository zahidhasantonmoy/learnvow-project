<?php

namespace App\Livewire;

use App\Models\Book;
use App\Models\Wishlist;
use Livewire\Component;

class WishlistButton extends Component
{
    public Book $book;
    public bool $inWishlist = false;

    public function mount(Book $book, bool $inWishlist = false): void
    {
        $this->book = $book;
        $this->inWishlist = $inWishlist;
    }

    public function toggle(): void
    {
        $user = auth()->user();
        if (! $user) {
            $this->redirectRoute('login', ['locale' => app()->getLocale()]);
            return;
        }

        $existing = Wishlist::where('user_id', $user->id)->where('book_id', $this->book->id)->first();

        if ($existing) {
            $existing->delete();
            $this->inWishlist = false;
        } else {
            Wishlist::create(['user_id' => $user->id, 'book_id' => $this->book->id]);
            $this->inWishlist = true;
        }
    }

    public function render()
    {
        return view('livewire.wishlist-button');
    }
}
