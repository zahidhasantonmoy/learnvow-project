<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\User;
use App\Services\CommunityService;
use Illuminate\Http\Request;

/** Community feed + find friends (Phase 4 spec §6.1, §6.3, §14.5). */
class CommunityController extends Controller
{
    use BuildsSeo;

    public function index(string $locale, CommunityService $community)
    {
        return view('community.index', [
            'items' => $community->feedFor(auth()->user()),
            'seo' => $this->seo(__('community.feed_title'), null, ['noindex' => true]),
        ]);
    }

    /** Search by name only — phone/email are never shown in results (§6.3). */
    public function findFriends(string $locale, Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        $users = collect();

        if ($query !== '') {
            $users = User::where('name', 'like', '%'.$query.'%')
                ->where('id', '!=', auth()->id())
                ->orderBy('name')->limit(20)->get();
        }

        return view('community.find-friends', [
            'query' => $query,
            'users' => $users,
            'seo' => $this->seo(__('community.find_friends_title'), null, ['noindex' => true]),
        ]);
    }
}
