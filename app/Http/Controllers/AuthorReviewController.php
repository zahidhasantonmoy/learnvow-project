<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Author;
use App\Models\Review;
use App\Services\AuthorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class AuthorReviewController extends Controller
{
    use BuildsSeo;

    /** Own books' approved reviews only (spec §1.8). */
    public function index(string $locale, AuthorService $authors)
    {
        Gate::authorize('viewStudio', Author::class);
        $author = $authors->approvedAuthorFor(auth()->user());
        abort_unless($author !== null, 403);

        $reviews = $authors->reviewsFor($author);

        // The author's existing replies, keyed by review id.
        $replies = \App\Models\ReviewReply::whereIn('review_id', $reviews->pluck('id'))
            ->get()->keyBy('review_id');

        return view('author.reviews', [
            'author' => $author,
            'reviews' => $reviews,
            'replies' => $replies,
            'seo' => $this->seo(__('author.reviews_title'), null, ['noindex' => true]),
        ]);
    }

    /** One public reply per review (editable; never deletable by the author). */
    public function reply(string $locale, Review $review, Request $request, AuthorService $authors)
    {
        Gate::authorize('viewStudio', Author::class);
        $author = $authors->approvedAuthorFor($request->user());
        abort_unless($author !== null, 403);

        $data = $request->validate(['body' => 'required|string|max:2000']);

        try {
            $authors->replyToReview($author, $review, $data['body']);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['body' => $e->getMessage()]);
        }

        return back()->with('status', __('author.reply_success'));
    }
}
