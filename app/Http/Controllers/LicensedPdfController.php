<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\UserBook;
use App\Services\BookFileService;
use App\Services\PdfWatermarkService;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Licensed print-ready PDF download (Phase 4 spec §2.4).
 *
 * Only a buyer of the book (user_books) AND allow_licensed_pdf=true.
 * Premium-only readers cannot download it (spec A6). Every page is
 * watermarked with the buyer's name + phone; the download is logged
 * in pdf_downloads. When watermarking is off in settings the button
 * is closed — no unwatermarked PDF is ever served.
 */
class LicensedPdfController extends Controller
{
    public function download(string $locale, Request $request, Book $book, BookFileService $files, PdfWatermarkService $watermarks)
    {
        $user = $request->user();

        $bought = UserBook::where('user_id', $user->id)->where('book_id', $book->id)->exists();

        if (! $bought) {
            // The layout renders only session('status') — withErrors alone
            // is invisible to the user (QA M5). Flash the message too.
            return back()->withErrors(['book' => __('print.licensed_pdf_not_bought')])
                ->with('status', __('print.licensed_pdf_not_bought'));
        }

        if (! $book->allow_licensed_pdf) {
            return back()->withErrors(['book' => __('print.licensed_pdf_unavailable')])
                ->with('status', __('print.licensed_pdf_unavailable'));
        }

        if ((new PayoutService)->setting('protection.watermark_enabled', '1') !== '1') {
            return back()->withErrors(['book' => __('print.watermark_unavailable')])
                ->with('status', __('print.watermark_unavailable'));
        }

        $file = $files->fileFor($book, 'pdf');
        abort_unless($file !== null, 404);

        try {
            $relativeDest = $watermarks->watermarkForBuyer($user, $book, $file, $request->ip());
        } catch (RuntimeException $e) {
            return back()->withErrors(['book' => $e->getMessage()])
                ->with('status', $e->getMessage());
        }

        return Storage::disk($file->disk)->download(
            $relativeDest, $book->slug.'-licensed.pdf', ['Content-Type' => 'application/pdf']
        );
    }
}
