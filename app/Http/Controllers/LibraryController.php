<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\ReadingProgress;
use App\Models\UserBook;
use Illuminate\Http\Request;

class LibraryController extends Controller
{
    use BuildsSeo;

    public function index(Request $request)
    {
        $books = UserBook::where('user_id', $request->user()->id)
            ->with('book.author', 'book.category')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        // X82 finished-book badge: books this user has read to 100%.
        $finishedBookIds = ReadingProgress::where('user_id', $request->user()->id)
            ->whereNotNull('finished_at')
            ->pluck('book_id')
            ->all();

        return view('library.index', [
            'userBooks' => $books,
            'finishedBookIds' => $finishedBookIds,
            'seo' => $this->seo(__('books.library_title'), null, ['noindex' => true]),
        ]);
    }
}
