<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Reading goals (spec §4.4). Degrades to an empty list until Builder A's table lands. */
class GoalController extends ApiController
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('reading_goals')) {
            return $this->ok([]);
        }

        $rows = DB::table('reading_goals')->where('user_id', $request->user()->id)
            ->orderByDesc('year')->orderBy('goal_type')->get();

        return $this->ok($rows->map(fn ($g) => [
            'id' => $g->id, 'year' => (int) $g->year, 'goal_type' => $g->goal_type,
            'target' => (int) $g->target, 'progress' => $this->progressFor($request->user()->id, $g),
        ])->values());
    }

    public function store(Request $request)
    {
        if (! Schema::hasTable('reading_goals')) {
            return $this->fail(__('api.goals_unavailable'), 503);
        }

        $data = $request->validate([
            'year' => 'required|integer|min:2020|max:2100',
            'goal_type' => 'required|in:books,minutes',
            'target' => 'required|integer|min:1|max:10000',
        ]);
        if ($data['goal_type'] === 'books' && $data['target'] > 365) {
            return $this->fail(__('api.goal_books_max'), 422);
        }
        if ($data['goal_type'] === 'minutes' && ($data['target'] < 60 || $data['target'] > 10000)) {
            return $this->fail(__('api.goal_minutes_range'), 422);
        }

        $user = $request->user();
        DB::table('reading_goals')->updateOrInsert(
            ['user_id' => $user->id, 'year' => $data['year'], 'goal_type' => $data['goal_type']],
            ['target' => $data['target'], 'updated_at' => now(), 'created_at' => now()]
        );

        return $this->ok(['year' => $data['year'], 'goal_type' => $data['goal_type'], 'target' => $data['target']], 201);
    }

    private function progressFor(int $userId, object $goal): int
    {
        if ($goal->goal_type === 'books' && Schema::hasTable('reading_progress')) {
            return (int) DB::table('reading_progress')->where('user_id', $userId)
                ->whereNotNull('finished_at')
                ->whereYear('finished_at', $goal->year)->count();
        }
        if ($goal->goal_type === 'minutes' && Schema::hasTable('reading_minutes')) {
            return (int) DB::table('reading_minutes')->where('user_id', $userId)
                ->where('period', 'like', $goal->year.'-%')->sum('minutes');
        }

        return 0;
    }
}
