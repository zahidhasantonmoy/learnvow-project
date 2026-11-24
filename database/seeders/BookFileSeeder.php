<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Services\BookFileService;
use Illuminate\Database\Seeder;
use ZipArchive;

/**
 * Demo EPUBs for the seeded books (spec §7.2).
 *
 * Minimal VALID EPUB per book: the book's description + a sample
 * chapter, clearly marked "Demo content". This is NOT the book's real
 * full text — real EPUBs arrive in Phase 3 (Author Studio upload) or
 * from files the user supplies. No seed audio files (§7.2).
 */
class BookFileSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(BookFileService::class);

        foreach (Book::all() as $book) {
            $service->storeDemoEpub($book, $this->buildDemoEpub($book));
        }

        // QA M5: ONE seeded book (Pride and Prejudice — not Prophet) gets
        // a real multi-page source PDF + allow_licensed_pdf=1, so the
        // watermarked licensed-PDF download is verifiable end to end.
        // The PDF is generated here with FPDF, never a committed binary.
        $licensed = Book::where('slug', 'pride-and-prejudice')->first();
        if ($licensed !== null) {
            $service->storeDemoPdf($licensed, $this->buildDemoPdf($licensed));
            $licensed->update(['allow_licensed_pdf' => true]);
        }
    }

    /** Build a minimal 3-page demo PDF (FPDF, ASCII text only). */
    private function buildDemoPdf(Book $book): string
    {
        $pdf = new \FPDF();
        $pdf->SetTitle($book->title.' (demo edition)');
        $pdf->SetAuthor('LearnVow (demo edition)');

        foreach ([1, 2, 3] as $page) {
            $pdf->AddPage();
            $pdf->SetFont('Helvetica', 'B', 16);
            $pdf->Cell(0, 12, $book->title.' - Demo page '.$page);
            $pdf->Ln(14);
            $pdf->SetFont('Helvetica', '', 12);
            $pdf->MultiCell(0, 8, 'Demo content. This is a demo licensed PDF for the LearnVow reader - not the book\'s full text. Page '.$page.' of 3.');
        }

        return $pdf->Output('S');
    }

    /** Build a minimal valid EPUB (EPUB 2) as raw bytes. */
    private function buildDemoEpub(Book $book): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lv-epub-');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);

        // The mimetype entry MUST be first and stored (uncompressed).
        $zip->addFromString('mimetype', 'application/epub+zip');
        $zip->setCompressionName('mimetype', ZipArchive::CM_STORE);

        $title = htmlspecialchars($book->title, ENT_XML1, 'UTF-8');
        $author = htmlspecialchars($book->author->name ?? 'LearnVow', ENT_XML1, 'UTF-8');
        $lang = $book->language === 'bn' ? 'bn' : 'en';
        $description = htmlspecialchars(
            $book->description ?? '',
            ENT_XML1,
            'UTF-8'
        );

        $zip->addFromString('META-INF/container.xml', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <container version="1.0" xmlns="urn:oasis:names:tc:opendocument:xmlns:container">
              <rootfiles>
                <rootfile full-path="OEBPS/content.opf" media-type="application/oebps-package+xml"/>
              </rootfiles>
            </container>
            XML);

        $zip->addFromString('OEBPS/content.opf', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <package xmlns="http://www.idpf.org/2007/opf" unique-identifier="bookid" version="2.0">
              <metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:opf="http://www.idpf.org/2007/opf">
                <dc:title>{$title}</dc:title>
                <dc:creator>{$author}</dc:creator>
                <dc:language>{$lang}</dc:language>
                <dc:identifier id="bookid">urn:learnvow:demo:{$book->slug}</dc:identifier>
                <dc:publisher>LearnVow (demo edition)</dc:publisher>
                <dc:description>Demo content — sample chapter for the LearnVow reader.</dc:description>
              </metadata>
              <manifest>
                <item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>
                <item id="ch1" href="chapter-1.xhtml" media-type="application/xhtml+xml"/>
                <item id="ch2" href="chapter-2.xhtml" media-type="application/xhtml+xml"/>
              </manifest>
              <spine toc="ncx">
                <itemref idref="ch1"/>
                <itemref idref="ch2"/>
              </spine>
            </package>
            XML);

        $zip->addFromString('OEBPS/toc.ncx', <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1">
              <head>
                <meta name="dtb:uid" content="urn:learnvow:demo:{$book->slug}"/>
                <meta name="dtb:depth" content="1"/>
                <meta name="dtb:totalPageCount" content="0"/>
                <meta name="dtb:maxPageNumber" content="0"/>
              </head>
              <docTitle><text>{$title}</text></docTitle>
              <navMap>
                <navPoint id="nav1" playOrder="1">
                  <navLabel><text>About this book</text></navLabel>
                  <content src="chapter-1.xhtml"/>
                </navPoint>
                <navPoint id="nav2" playOrder="2">
                  <navLabel><text>Sample chapter</text></navLabel>
                  <content src="chapter-2.xhtml"/>
                </navPoint>
              </navMap>
            </ncx>
            XML);

        $zip->addFromString('OEBPS/chapter-1.xhtml', <<<HTML
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
            <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$lang}">
            <head><title>About this book</title></head>
            <body>
            <h1>{$title}</h1>
            <p><strong>Demo content.</strong> This is a demo EPUB for the LearnVow reader — not the book's full text.</p>
            <p>{$description}</p>
            <p>Author: {$author}</p>
            </body>
            </html>
            HTML);

        $zip->addFromString('OEBPS/chapter-2.xhtml', <<<HTML
            <?xml version="1.0" encoding="UTF-8"?>
            <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
            <html xmlns="http://www.w3.org/1999/xhtml" xml:lang="{$lang}">
            <head><title>Sample chapter</title></head>
            <body>
            <h1>Sample chapter</h1>
            <p><strong>Demo content.</strong> The full book text arrives with the real EPUB in Phase 3.</p>
            <p>{$description}</p>
            <p>Thank you for reading with LearnVow — শেখার অঙ্গীকার।</p>
            </body>
            </html>
            HTML);

        $zip->close();

        $bytes = file_get_contents($tmp);
        unlink($tmp);

        return $bytes;
    }
}
