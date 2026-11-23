<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Models\ReadingMinute;
use App\Models\ReadingProgress;
use Illuminate\Support\Facades\DB;

class AuthorAnalyticsController extends Controller
{
    use BuildsSeo;

    /** Author — Reading Analytics (spec §10.1 authorAnalytics): reading time for the author's OWN books only. */
    public function index(string $locale)
    {
        $author = $this->author();

        if ($author === null) {
            return redirect('/'.$locale.'/author/apply');
        }

        $bookIds = $author->books()->pluck('id');

        $byBook = ReadingMinute::whereIn('book_id', $bookIds)
            ->select('book_id',
                DB::raw('SUM(minutes) as total_minutes'),
                DB::raw('SUM(CASE WHEN counted = 1 THEN minutes ELSE 0 END) as counted_minutes'),
                DB::raw('COUNT(DISTINCT user_id) as readers'))
            ->groupBy('book_id')
            ->with('book')
            ->get();

        $progress = ReadingProgress::whereIn('book_id', $bookIds)
            ->select('book_id',
                DB::raw('COUNT(*) as readers'),
                DB::raw('AVG(percent) as avg_percent'),
                DB::raw('SUM(CASE WHEN percent >= 100 THEN 1 ELSE 0 END) as finished'))
            ->groupBy('book_id')
            ->get()
            ->keyBy('book_id');

        $totalMinutes = (int) $byBook->sum('total_minutes');
        $countedMinutes = (int) $byBook->sum('counted_minutes');

        return view('author.analytics', [
            'author' => $author,
            'byBook' => $byBook,
            'progress' => $progress,
            'totalMinutes' => $totalMinutes,
            'countedMinutes' => $countedMinutes,
            'seo' => $this->seo(__('payouts.analytics_title'), null, ['noindex' => true]),
        ]);
    }

    protected function author(): ?Author
    {
        $user = auth()->user();

        if ($user === null) {
            return null;
        }

        return Author::where('user_id', $user->id)->first();
    }
}
