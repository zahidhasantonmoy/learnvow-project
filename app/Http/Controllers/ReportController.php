<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Book;
use App\Models\Report;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * User-facing report entry points (spec §5.2, M1). Any logged-in user
 * may report a book or a review; the decision flow lives in
 * AdminService::decideReport() (Filament ReportResource).
 */
class ReportController extends Controller
{
    use BuildsSeo;

    public const REASONS = ['copyright', 'spam', 'abuse', 'wrong_content', 'other'];

    public function storeBook(string $locale, string $slug, Request $request): RedirectResponse
    {
        $book = Book::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', self::REASONS)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        Report::create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => Report::TYPE_BOOK,
            'reportable_id' => $book->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);

        return back()->with('status', __('moderation.reports.report_submitted'));
    }

    public function storeReview(string $locale, Review $review, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', self::REASONS)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        Report::create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => Report::TYPE_REVIEW,
            'reportable_id' => $review->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
        ]);

        return back()->with('status', __('moderation.reports.report_submitted'));
    }
}
