<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\BookRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Request a book LearnVow does not have (§4.9). Max 5 per month. */
class BookRequestController extends Controller
{
    use BuildsSeo;

    public function create(Request $request, string $locale)
    {
        $topRequested = BookRequest::select('title', DB::raw('COUNT(*) as requests_count'))
            ->groupBy('title')->orderByDesc('requests_count')->limit(10)->get();

        return view('books.request', [
            'topRequested' => $topRequested,
            'seo' => $this->seo(__('books.request_book_title'), null, ['noindex' => true]),
        ]);
    }

    public function store(Request $request, string $locale)
    {
        $user = $request->user();

        $thisMonth = BookRequest::where('user_id', $user->id)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        if ($thisMonth >= BookRequest::MAX_PER_MONTH) {
            throw ValidationException::withMessages([
                'title' => __('books.request_monthly_limit', ['max' => BookRequest::MAX_PER_MONTH]),
            ]);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $bookRequest = BookRequest::create(array_merge($data, ['user_id' => $user->id]));

        $sameTitleCount = BookRequest::where('title', $data['title'])->count();

        return back()->with('status', __('books.request_submitted', ['count' => $sameTitleCount]));
    }
}
