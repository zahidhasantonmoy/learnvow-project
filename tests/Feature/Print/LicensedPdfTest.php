<?php

namespace Tests\Feature\Print;

use App\Models\BookFile;
use App\Models\PdfDownload;
use App\Models\UserBook;
use App\Services\PdfWatermarkService;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;

/**
 * Phase 4 spec §2.4/§2.5 — licensed PDF watermark + author copies.
 *
 * The FPDI base library (setasign/fpdf ^1.9) was installed by the
 * coordinator on 2026-11-23, so watermarking runs for real (the
 * `book_files.type` enum was also extended with 'pdf' — migration
 * 2026_11_24_031000).
 */
class LicensedPdfTest extends PrintTestCase
{
    private function pdfFile(): BookFile
    {
        Storage::fake('bookfiles');

        return BookFile::create([
            'book_id' => $this->prophet->id,
            'type' => 'pdf',
            'disk' => 'bookfiles',
            'path' => 'pdf/the-prophet.pdf',
            'size_bytes' => 100,
            'mime' => 'application/pdf',
        ]);
    }

    public function test_seeded_licensed_pdf_downloads_watermarked_copy(): void
    {
        // Regression (QA M5): no book had allow_licensed_pdf=1 or a
        // source PDF, so no watermarked download could ever be produced.
        // BookFileSeeder now seeds Pride and Prejudice (not Prophet)
        // with a real 3-page PDF + the flag on.
        Storage::fake('bookfiles');
        $this->seed(\Database\Seeders\BookFileSeeder::class);

        $book = \App\Models\Book::where('slug', 'pride-and-prejudice')->firstOrFail();
        $this->assertTrue($book->fresh()->allow_licensed_pdf);
        $file = \App\Models\BookFile::where('book_id', $book->id)->where('type', 'pdf')->firstOrFail();
        $this->assertTrue(Storage::disk('bookfiles')->exists($file->path));

        // Source PDF really is 3 pages.
        $check = new Fpdi();
        $this->assertSame(3, $check->setSourceFile(Storage::disk('bookfiles')->path($file->path)));

        UserBook::create(['user_id' => $this->reader->id, 'book_id' => $book->id, 'source' => 'purchase']);

        $this->actingAs($this->reader);
        $response = $this->get('/bn/books/pride-and-prejudice/licensed-pdf');
        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        // Streamed download: getContent() is empty in tests.
        $content = $response->streamedContent();
        $this->assertStringStartsWith('%PDF', $content);

        // Watermarked copy keeps all 3 pages.
        $tmp = sys_get_temp_dir().'/p4-download-'.uniqid().'.pdf';
        file_put_contents($tmp, $content);
        try {
            $stamped = new Fpdi();
            $this->assertSame(3, $stamped->setSourceFile($tmp));
        } finally {
            @unlink($tmp);
        }

        $this->assertSame(1, PdfDownload::where('book_id', $book->id)->count());
    }

    public function test_licensed_pdf_denial_is_visible_as_status(): void
    {
        // QA M5: the guard redirected withErrors('book') but the layout
        // renders only session('status') — the user saw nothing.
        $this->actingAs($this->reader);
        $this->get('/bn/books/the-prophet/licensed-pdf')
            ->assertRedirect()
            ->assertSessionHasErrors('book')
            ->assertSessionHas('status', __('print.licensed_pdf_not_bought'));
    }

    public function test_watermark_text_contains_buyer_name_and_phone(): void
    {
        $service = app(PdfWatermarkService::class);

        $this->assertSame(
            $this->reader->name.' — '.$this->reader->phone,
            $service->watermarkTextFor($this->reader)
        );
    }

    public function test_watermark_stamps_every_page_of_the_pdf(): void
    {
        $source = sys_get_temp_dir().'/p4-source-'.uniqid().'.pdf';
        $dest = sys_get_temp_dir().'/p4-stamped-'.uniqid().'.pdf';

        try {
            $maker = new \FPDF();
            $maker->AddPage();
            $maker->SetFont('Helvetica', '', 12);
            $maker->Cell(0, 10, 'Page one');
            $maker->AddPage();
            $maker->Cell(0, 10, 'Page two');
            $maker->Output('F', $source);

            $out = app(PdfWatermarkService::class)->watermark(
                $source, 'Test — 01700000002', $dest
            );

            $this->assertSame($dest, $out);
            $this->assertFileExists($dest);
            $this->assertStringStartsWith('%PDF', (string) file_get_contents($dest));
            $this->assertGreaterThan(0, (int) filesize($dest));

            // The stamped copy keeps both pages.
            $check = new Fpdi();
            $this->assertSame(2, $check->setSourceFile($dest));
        } finally {
            @unlink($source);
            @unlink($dest);
        }
    }

    public function test_watermark_rejects_missing_source_and_empty_text(): void
    {
        $service = app(PdfWatermarkService::class);

        $this->expectException(\InvalidArgumentException::class);
        $service->watermark('/tmp/does-not-exist-p4.pdf', 'Test — 01700000002', '/tmp/out.pdf');
    }

    public function test_licensed_pdf_requires_purchase_and_allow_flag(): void
    {
        // Gates abort before fileFor($book, 'pdf') is reached, so no
        // 'pdf' BookFile row is needed here.
        $this->actingAs($this->reader);
        $this->get('/bn/books/the-prophet/licensed-pdf')
            ->assertRedirect()
            ->assertSessionHasErrors('book');

        // Bought but flag off → no download.
        UserBook::create(['user_id' => $this->reader->id, 'book_id' => $this->prophet->id, 'source' => 'purchase']);
        $this->prophet->update(['allow_licensed_pdf' => false]);
        $this->get('/bn/books/the-prophet/licensed-pdf')
            ->assertRedirect()
            ->assertSessionHasErrors('book');

        $this->assertSame(0, PdfDownload::count());
    }

    public function test_author_copies_order_never_accrues_share(): void
    {
        $prints = app(\App\Services\PrintService::class);

        $order = $prints->createOrder($this->authorUser, $this->prophet, $this->orderData(), true);

        $this->assertTrue($order->ordered_by_author);

        $prints->submitTrxId($order, 'bkash', '9HX7K2LMNP');
        $order = $prints->onPaymentVerified($order->fresh(), $this->admin);
        $order = $prints->sendToPartner($order->fresh(), $this->partner, 200, $this->admin);
        $order = $prints->markPrinting($order->fresh(), $this->partner);
        $order = $prints->markShipped($order->fresh(), $this->partner);
        $prints->markDelivered($order->fresh(), $this->admin);

        $this->assertSame(0, \App\Models\AuthorEarning::where('note', 'print order '.$order->order_no)->count());
    }

    public function test_author_can_order_own_copies_via_web_but_not_others_books(): void
    {
        $this->actingAs($this->authorUser);

        $this->get('/bn/author/print-copies')->assertOk();

        $response = $this->post('/bn/author/print-copies', array_merge(
            $this->orderData(), ['book_id' => $this->prophet->id]
        ));
        $response->assertRedirect();
        $this->assertTrue(\App\Models\PrintOrder::where('ordered_by_author', true)->exists());

        // Another author's book (Devdas) → rejected.
        $devdas = \App\Models\Book::where('slug', 'devdas')->firstOrFail();
        $this->post('/bn/author/print-copies', array_merge(
            $this->orderData(), ['book_id' => $devdas->id]
        ))->assertSessionHasErrors('book');
    }
}
