<?php

namespace App\Http\Controllers;

use App\Models\Club;
use App\Models\CommunityPost;
use App\Services\CommunityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/** Club discussion posts (Phase 4 spec §6.2, §14.5). */
class ClubPostController extends Controller
{
    /** Livewire ClubDiscussion also posts through this service. */
    public function store(string $locale, Request $request, Club $club, CommunityService $community)
    {
        Gate::authorize('post', $club);

        $data = $request->validate([
            'body' => 'required|string|max:5000',
            'type' => 'nullable|in:discussion,review_share,activity',
            'book_id' => 'nullable|exists:books,id',
        ]);

        try {
            $community->createPost($request->user(), $club, $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['body' => $e->getMessage()])->withInput();
        }

        return back()->with('status', __('community.post_created'));
    }

    public function destroy(string $locale, Club $club, CommunityPost $post, CommunityService $community)
    {
        if ((int) $post->club_id !== (int) $club->id) {
            abort(404);
        }

        Gate::authorize('delete', $post);
        $community->deletePost(auth()->user(), $post);

        return back()->with('status', __('community.post_deleted'));
    }
}
