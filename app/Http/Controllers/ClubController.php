<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Club;
use App\Services\CommunityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/** Book clubs (Phase 4 spec §6.2, §14.5). */
class ClubController extends Controller
{
    use BuildsSeo;

    public function index(string $locale, CommunityService $community)
    {
        return view('community.clubs.index', [
            'clubs' => $community->clubsFor(auth()->user()),
            'seo' => $this->seo(__('community.clubs_title'), null, ['noindex' => true]),
        ]);
    }

    public function store(string $locale, Request $request, CommunityService $community)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'is_public' => 'nullable|boolean',
            'reading_goal' => 'nullable|string|max:255',
        ]);

        try {
            $club = $community->createClub($request->user(), $data);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['name' => $e->getMessage()])->withInput();
        }

        return redirect()->route('clubs.show', ['locale' => $locale, 'club' => $club->id])
            ->with('status', __('community.club_created'));
    }

    public function show(string $locale, Club $club)
    {
        Gate::authorize('view', $club);

        $posts = $club->posts()->with(['user', 'book'])
            ->where('status', \App\Models\CommunityPost::STATUS_PUBLISHED)
            ->latest()->paginate(20);

        return view('community.clubs.show', [
            'club' => $club->load('members.user'),
            'posts' => $posts,
            'seo' => $this->seo($club->name, null, ['noindex' => true]),
        ]);
    }

    public function join(string $locale, Club $club, CommunityService $community)
    {
        Gate::authorize('join', $club);

        try {
            $community->joinClub(auth()->user(), $club);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['club' => $e->getMessage()]);
        }

        return back()->with('status', __('community.joined'));
    }

    public function leave(string $locale, Club $club, CommunityService $community)
    {
        try {
            $community->leaveClub(auth()->user(), $club);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['club' => $e->getMessage()]);
        }

        return back()->with('status', __('community.left'));
    }
}
