<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\Badge;
use App\Models\UserBadge;
use Illuminate\Http\Request;

/** Badges & achievements (§4.5): earned + not-yet-earned. */
class BadgeController extends Controller
{
    use BuildsSeo;

    public function index(Request $request, string $locale)
    {
        $user = $request->user();
        $earnedIds = UserBadge::where('user_id', $user->id)->pluck('badge_id')->all();

        return view('profile.badges', [
            'badges' => Badge::orderBy('id')->get(),
            'earnedIds' => $earnedIds,
            'earned' => UserBadge::where('user_id', $user->id)->with('badge')->latest('awarded_at')->get(),
            'seo' => $this->seo(__('books.badges_title'), null, ['noindex' => true]),
        ]);
    }
}
