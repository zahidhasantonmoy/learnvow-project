<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Book file access — every file goes through here (spec §5).
 *
 * LOCAL-ONLY (user decision 2026-11-22): R2 is added at production
 * time. Until then the `bookfiles` local disk holds the demo EPUBs and
 * the 15-minute signed URL points at a controller route that streams
 * the file after re-checking entitlement — never a direct file link,
 * and the raw path is never exposed to Blade/JSON.
 *
 * When R2 arrives, only this class changes: disk() reads the env and
 * temporaryUrlFor() switches to the R2 temporary URL. No controller
 * or Livewire code may call Storage::disk('r2') directly.
 */
class BookFileService
{
    /** Disk name for book files: 'local' now, 'r2' at production. */
    public function disk(): string
    {
        return config('filesystems.bookfiles_disk', 'bookfiles');
    }

    /** The BookFile row for a book + type, or null. */
    public function fileFor(Book $book, string $type = 'epub'): ?BookFile
    {
        return BookFile::where('book_id', $book->id)
            ->where('type', $type)
            ->first();
    }

    /**
     * 15-minute signed URL for a book file. Locally this is a signed
     * route to ReaderController@file (which re-checks canRead and then
     * streams); on R2 it will be the bucket temporary URL.
     */
    public function signedFileUrl(Book $book, string $type = 'epub'): string
    {
        if ($this->disk() === 'r2') {
            $file = $this->fileFor($book, $type);

            if ($file === null) {
                abort(404);
            }

            return Storage::disk('r2')->temporaryUrl($file->path, now()->addMinutes(15));
        }

        return URL::temporarySignedRoute(
            'reader.file.signed',
            now()->addMinutes(15),
            ['book' => $book->id, 'type' => $type]
        );
    }

    /**
     * Stream a stored file to the browser (local disk only).
     * Audio streams inline so <audio> can play it; everything else
     * downloads as an attachment.
     */
    public function streamFile(BookFile $file): StreamedResponse
    {
        $disk = Storage::disk($file->disk);

        abort_unless($disk->exists($file->path), 404);

        $mime = $file->mime ?? 'application/octet-stream';

        if (str_starts_with($mime, 'audio/')) {
            return response()->streamDownload(function () use ($disk, $file) {
                echo $disk->get($file->path);
            }, basename($file->path), [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.basename($file->path).'"',
                'Accept-Ranges' => 'bytes',
            ]);
        }

        return $disk->download($file->path, basename($file->path), [
            'Content-Type' => $mime,
        ]);
    }

    /** Store demo EPUB content for a seeded book (spec §7.2). */
    public function storeDemoEpub(Book $book, string $content): BookFile
    {
        $path = 'epub/'.$book->slug.'.epub';

        Storage::disk($this->disk())->put($path, $content);

        return BookFile::updateOrCreate(
            ['book_id' => $book->id, 'type' => 'epub'],
            [
                'disk' => $this->disk(),
                'path' => $path,
                'size_bytes' => strlen($content),
                'mime' => 'application/epub+zip',
            ]
        );
    }

    /**
     * Presigned PUT for direct author uploads (spec §5.1) — R2 only.
     * Not available on the local disk; Phase 3's upload UI needs R2.
     */
    public function presignedPutUrl(string $path, string $mime): string
    {
        throw new \LogicException('Direct uploads need the R2 disk (production). Local disk has no presigned PUT.');
    }
}
