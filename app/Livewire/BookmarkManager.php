<?php

namespace App\Livewire;

use App\Models\Bookmark;
use App\Models\Highlight;
use App\Models\Note;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * BookmarkManager — the bookmarks screen's list (§10): all of the
 * reader's bookmarks / highlights / notes across books, with hard
 * delete. Clicking an item opens the reader at that CFI.
 */
class BookmarkManager extends Component
{
    public function deleteBookmark(int $id): void
    {
        $bookmark = Bookmark::findOrFail($id);
        abort_unless($bookmark->user_id === Auth::id(), 403);
        $bookmark->delete();
    }

    public function deleteHighlight(int $id): void
    {
        $highlight = Highlight::findOrFail($id);
        abort_unless($highlight->user_id === Auth::id(), 403);
        $highlight->delete();
    }

    public function deleteNote(int $id): void
    {
        $note = Note::findOrFail($id);
        abort_unless($note->user_id === Auth::id(), 403);
        $note->delete();
    }

    public function render()
    {
        $userId = Auth::id();

        return view('livewire.bookmark-manager', [
            'bookmarks' => Bookmark::where('user_id', $userId)->with('book')->latest()->get(),
            'highlights' => Highlight::where('user_id', $userId)->with('book')->latest()->get(),
            'notes' => Note::where('user_id', $userId)->with('book')->latest()->get(),
        ]);
    }
}
