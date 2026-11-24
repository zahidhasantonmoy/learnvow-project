<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** My badges (spec §4.5). Empty until Builder A's tables land. */
class BadgeController extends ApiController
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('badges')) {
            return $this->ok([]);
        }

        $earned = [];
        if (Schema::hasTable('user_badges')) {
            $earned = DB::table('user_badges')->where('user_id', $request->user()->id)
                ->pluck('awarded_at', 'badge_id')->all();
        }

        $badges = DB::table('badges')->orderBy('id')->get();

        return $this->ok($badges->map(fn ($b) => [
            'id' => $b->id, 'slug' => $b->slug, 'name' => $b->name, 'name_en' => $b->name_en,
            'description' => $b->description, 'icon' => $b->icon,
            'earned' => array_key_exists($b->id, $earned),
            'awarded_at' => $earned[$b->id] ?? null,
        ])->values());
    }
}
