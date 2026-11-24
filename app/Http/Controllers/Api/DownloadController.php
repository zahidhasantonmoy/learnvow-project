<?php

namespace App\Http\Controllers\Api;

use App\Models\Book;
use App\Models\Download;
use App\Services\BookFileService;
use App\Services\BookService;
use App\Services\DownloadEncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Encrypted download (spec §5.4) + download-key endpoint (A11). */
class DownloadController extends ApiController
{
    public function store(Request $request, Book $book, BookFileService $files, BookService $books, DownloadEncryptionService $crypto)
    {
        if (! $books->canRead($request->user(), $book)) {
            return $this->fail(__('api.cannot_read'), 403);
        }

        $file = $files->fileFor($book, 'epub');
        if ($file === null) {
            abort(404);
        }

        $encrypted = $crypto->encryptedDownloadsEnabled();
        $download = Download::create([
            'user_id' => $request->user()->id,
            'book_id' => $book->id,
            'file_id' => $file->id,
            'size_bytes' => $file->size_bytes,
            'downloaded_at' => now(),
        ]);
        // Phase 4 columns (§12.2) are not in Download::$fillable (not
        // Builder B's model) — set them explicitly instead.
        $download->forceFill([
            'is_encrypted' => $encrypted,
            'expires_at' => null,
            'encryption_key_id' => $encrypted ? $crypto->keyId() : null,
        ])->save();

        return $this->ok([
            'download_id' => $download->id,
            'is_encrypted' => $encrypted,
            'encryption_key_id' => $download->encryption_key_id,
            'url' => route('api.v1.reader.download.file', ['book' => $book->slug, 'download' => $download->id]),
            'download_key_url' => $encrypted ? route('api.v1.reader.download-key', ['book' => $book->slug]) : null,
        ], 201);
    }

    /** Stream the download file; encrypted when the row says so. */
    public function file(Request $request, Book $book, Download $download, DownloadEncryptionService $crypto)
    {
        $user = $request->user();
        if ((int) $download->user_id !== (int) $user->id || (int) $download->book_id !== (int) $book->id) {
            abort(403);
        }

        $file = $download->file;
        if ($file === null || ! Storage::disk($file->disk)->exists($file->path)) {
            abort(404);
        }

        $contents = Storage::disk($file->disk)->get($file->path);
        $mime = $file->mime ?? 'application/octet-stream';

        if ($download->is_encrypted) {
            return response($crypto->encrypt($contents, $user), 200, [
                'Content-Type' => 'application/octet-stream',
                'Content-Disposition' => 'attachment; filename="'.$book->slug.'.encrypted"',
                'X-Encryption-Key-Id' => (string) $download->encryption_key_id,
                'X-Encryption-Algorithm' => DownloadEncryptionService::ALGORITHM,
            ]);
        }

        return response($contents, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.basename($file->path).'"',
        ]);
    }

    /** Download key (A11): auth + canRead checked, key never in a URL. */
    public function downloadKey(Request $request, Book $book, BookService $books, DownloadEncryptionService $crypto)
    {
        if (! $books->canRead($request->user(), $book)) {
            return $this->fail(__('api.cannot_read'), 403);
        }

        return $this->ok($crypto->downloadKey($request->user()));
    }
}
