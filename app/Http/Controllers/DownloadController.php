<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\Download;
use App\Services\BookFileService;
use App\Services\BookService;
use Illuminate\Http\Request;

class DownloadController extends Controller
{
    use BuildsSeo;

    public function __construct(
        protected BookService $books,
        protected BookFileService $files,
    ) {}

    /** Downloaded books + their sizes (§1.7). Sizes are the real file sizes. */
    public function index(Request $request, string $locale)
    {
        $downloads = Download::where('user_id', $request->user()->id)
            ->with('book.author')
            ->latest('downloaded_at')
            ->paginate(12);

        return view('library.downloads', [
            'downloads' => $downloads,
            'seo' => $this->seo(__('reader.downloads_title'), null, ['noindex' => true]),
        ]);
    }

    /** Download an entitled book — every download is logged (§1.7). */
    public function store(Request $request, string $locale, Book $book)
    {
        abort_unless($this->books->canRead($request->user(), $book), 403);

        $file = $this->files->fileFor($book, 'epub');
        abort_unless($file !== null, 404);

        Download::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            'file_id' => $file->id,
            'size_bytes' => $file->size_bytes,
            'downloaded_at' => now(),
        ]);

        return redirect()->away($this->files->signedFileUrl($book, 'epub'));
    }

    /** Remove a download log row (hard delete; the file itself stays). */
    public function destroy(Request $request, string $locale, Download $download)
    {
        abort_unless($download->user_id === $request->user()->id, 403);

        $download->delete();

        return back()->with('status', __('reader.download_deleted'));
    }
}
