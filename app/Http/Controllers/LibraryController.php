<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
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

        return view('library.index', [
            'userBooks' => $books,
            'seo' => $this->seo(__('books.library_title'), null, ['noindex' => true]),
        ]);
    }
}
