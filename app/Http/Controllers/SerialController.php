<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Follow;
use App\Models\Serial;
use App\Models\SerialEpisode;
use App\Notifications\AuthorNotification;
use App\Services\BookService;
use Illuminate\Http\Request;

/**
 * Serial detail + episode reading (§4.7).
 * Subscribe = follow the serial's author (no serial_subscriptions
 * table exists in spec §12.1 — see report); publishing an episode
 * notifies that author's followers.
 */
class SerialController extends Controller
{
    use BuildsSeo;

    public function __construct(protected BookService $books) {}

    public function show(Request $request, string $locale, Serial $serial)
    {
        return view('serials.show', [
            'serial' => $serial->load(['author', 'episodes']),
            'subscribed' => Follow::where('user_id', $request->user()->id)
                ->where('author_id', $serial->author_id)->exists(),
            'seo' => $this->seo($serial->title, null, ['noindex' => true]),
        ]);
    }

    public function episode(Request $request, string $locale, Serial $serial, SerialEpisode $episode)
    {
        abort_unless($episode->serial_id === $serial->id, 404);
        abort_unless($episode->isPublished(), 404);
        abort_unless($this->canReadEpisode($request->user(), $serial, $episode), 403);

        return view('serials.episode', [
            'serial' => $serial,
            'episode' => $episode,
            'seo' => $this->seo($episode->title, null, ['noindex' => true]),
        ]);
    }

    /** Toggle "notify me of the next episode" (follow the author). */
    public function subscribe(Request $request, string $locale, Serial $serial)
    {
        $user = $request->user();
        $existing = Follow::where('user_id', $user->id)->where('author_id', $serial->author_id)->first();

        if ($existing) {
            $existing->delete();
            $message = __('books.serial_unsubscribed');
        } else {
            Follow::create(['user_id' => $user->id, 'author_id' => $serial->author_id]);
            $message = __('books.serial_subscribed');
        }

        return back()->with('status', $message);
    }

    /** Notify the author's followers that a new episode is out. */
    public static function notifyFollowers(Serial $serial, SerialEpisode $episode): void
    {
        $followerIds = Follow::where('author_id', $serial->author_id)->pluck('user_id')->all();

        foreach ($followerIds as $userId) {
            $user = \App\Models\User::find($userId);
            $user?->notify(new AuthorNotification('serial_episode', [
                'title' => $serial->title,
                'serial_id' => $serial->id,
                'episode_no' => $episode->episode_no,
            ]));
        }
    }

    protected function canReadEpisode($user, Serial $serial, SerialEpisode $episode): bool
    {
        if ($episode->is_free || $serial->is_free) {
            return true;
        }

        if ($serial->is_subscription_included && $this->books->hasActivePremium($user)) {
            return true;
        }

        return false;
    }
}
