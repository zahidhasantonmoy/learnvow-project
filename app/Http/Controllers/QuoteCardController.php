<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\Highlight;
use Illuminate\Http\Request;

/**
 * Quote card (§4.6). Rendered on demand from the user's own highlight
 * — never stored (A14). Quotes over 280 chars are truncated with "…".
 */
class QuoteCardController extends Controller
{
    use BuildsSeo;

    public const MAX_LENGTH = 280;

    public function show(Request $request, string $locale, Book $book)
    {
        $user = $request->user();

        // An explicitly requested highlight that is not the user's own
        // stays a 404 (another user's highlight must never render).
        // With no highlight at all, render the empty state instead of
        // a bare 404 (QA M2): the reader is told to highlight a line.
        $requested = $request->filled('highlight');

        $highlight = null;
        if ($requested) {
            $highlight = Highlight::where('user_id', $user->id)
                ->where('book_id', $book->id)
                ->find($request->input('highlight'));
            abort_if($highlight === null, 404);
        } else {
            $highlight = Highlight::where('user_id', $user->id)
                ->where('book_id', $book->id)->latest()->first();
        }

        if ($highlight === null) {
            return view('reader.quote-card', [
                'book' => $book,
                'highlight' => null,
                'quote' => '',
                'seo' => $this->seo(__('reader.quote_card_title'), null, ['noindex' => true]),
            ]);
        }

        $quote = $highlight->note ?? '';
        if (mb_strlen($quote) > self::MAX_LENGTH) {
            $quote = mb_substr($quote, 0, self::MAX_LENGTH - 1).'…';
        }

        return view('reader.quote-card', [
            'book' => $book,
            'highlight' => $highlight,
            'quote' => $quote,
            'seo' => $this->seo(__('reader.quote_card_title'), null, ['noindex' => true]),
        ]);
    }
}
