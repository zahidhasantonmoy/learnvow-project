<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsSeo;
use App\Models\ReadingGoal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Reading goals (§4.4). Progress is live-computed on the model. */
class GoalController extends Controller
{
    use BuildsSeo;

    public function index(Request $request, string $locale)
    {
        return view('profile.goals', [
            'goals' => ReadingGoal::where('user_id', $request->user()->id)
                ->orderByDesc('year')->orderBy('goal_type')->get(),
            'seo' => $this->seo(__('goals.title'), null, ['noindex' => true]),
        ]);
    }

    public function store(Request $request, string $locale)
    {
        $data = $request->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'goal_type' => ['required', Rule::in(['books', 'minutes'])],
            'target' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        // Range per type (§4.4, A9): 1–365 books, 60–10000 minutes.
        $min = $data['goal_type'] === 'books' ? 1 : 60;
        $max = $data['goal_type'] === 'books' ? 365 : 10000;
        if ($data['target'] < $min || $data['target'] > $max) {
            return back()->withErrors(['target' => __('goals.target_range', ['min' => $min, 'max' => $max])])->withInput();
        }

        ReadingGoal::updateOrCreate(
            ['user_id' => $request->user()->id, 'year' => $data['year'], 'goal_type' => $data['goal_type']],
            ['target' => $data['target']]
        );

        return back()->with('status', __('goals.saved'));
    }

    public function destroy(Request $request, string $locale, ReadingGoal $goal)
    {
        // Own goals only (GoalPolicy states the same rule; the policy
        // class needs explicit registration — see report).
        abort_unless($goal->user_id === $request->user()->id, 403);
        $goal->delete();

        return back()->with('status', __('goals.deleted'));
    }
}
