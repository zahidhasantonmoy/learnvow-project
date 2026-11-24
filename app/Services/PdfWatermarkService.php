<?php

namespace App\Services;

use App\Models\Book;
use App\Models\BookFile;
use App\Models\PdfDownload;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * Licensed-PDF watermarking (Phase 4 spec §2.4).
 *
 * Every page of the downloaded PDF is stamped with the buyer's name +
 * phone. The source file is never modified — a watermarked copy is
 * written to $destPath and streamed from there.
 *
 * NOTE (2026-11-23): the FPDI base library IS installed now —
 * the coordinator added `setasign/fpdf` ^1.9, so `Fpdi` instantiates
 * and watermark() runs for real. The guard below stays: if the base
 * class ever goes missing, watermarking must fail loudly instead of
 * silently serving an unwatermarked file. Watermarking without a
 * stamp is never allowed (spec §2.4).
 */
class PdfWatermarkService
{
    /** "Name — phone" stamp text for a buyer. */
    public function watermarkTextFor(User $user): string
    {
        $phone = $user->phone ?? '';

        return trim($user->name.' — '.$phone, " —");
    }

    /**
     * Write a watermarked copy of $sourcePath to $destPath.
     *
     * @throws \RuntimeException when no FPDI base PDF class is installed.
     */
    public function watermark(string $sourcePath, string $text, string $destPath): string
    {
        // Check the base class FIRST: autoloading Fpdi itself fatals when
        // its FPDF parent is missing, so it must never be touched before
        // this guard passes.
        if (! class_exists(\FPDF::class)) {
            throw new \RuntimeException('PDF watermarking needs an FPDI base library (setasign/fpdf) — not installed.');
        }

        if (! is_file($sourcePath)) {
            throw new \InvalidArgumentException('Source PDF not found.');
        }

        if (trim($text) === '') {
            throw new \InvalidArgumentException('Watermark text is required — an unwatermarked PDF must never be served.');
        }

        $pdf = new Fpdi();

        // The footer stamp sits at height-12, below FPDF's default
        // page-break trigger (height-20) — without this, Cell() would
        // auto-break a blank page after every stamped page. Pages are
        // all added explicitly by the loop below, so auto-break is off.
        $pdf->SetAutoPageBreak(false);

        $pageCount = $pdf->setSourceFile($sourcePath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->SetTextColor(120, 120, 120);
            // Footer stamp (every page) + header stamp (every page).
            $pdf->SetXY(10, $size['height'] - 12);
            $pdf->Cell(0, 6, $text, 0, 0, 'C');
            $pdf->SetXY(10, 4);
            $pdf->Cell(0, 6, $text, 0, 0, 'C');
        }

        $dir = dirname($destPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $pdf->Output('F', $destPath);

        return $destPath;
    }

    /**
     * Watermark a book's PDF for a buyer and log the download.
     * Returns the path of the watermarked copy.
     */
    public function watermarkForBuyer(User $user, Book $book, BookFile $file, ?string $ip = null): string
    {
        $text = $this->watermarkTextFor($user);
        $source = Storage::disk($file->disk)->path($file->path);
        $relativeDest = 'watermarked/'.uniqid('pdf_', true).'.pdf';

        $this->watermark($source, $text, Storage::disk($file->disk)->path($relativeDest));

        PdfDownload::create([
            'user_id' => $user->id,
            'book_id' => $book->id,
            'file_id' => $file->id,
            'watermark_text' => $text,
            'ip_address' => $ip,
            'downloaded_at' => now(),
        ]);

        return $relativeDest;
    }
}
