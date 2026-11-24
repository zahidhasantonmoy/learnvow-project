<?php

namespace App\Livewire;

use App\Models\ReadingGoal;
use App\Services\BadgeService;
use Livewire\Component;

/** Set/edit/delete reading goals and show live progress (§4.4). */
class GoalTracker extends Component
{
    public int $year;

    public string $goalType = 'books';

    public int $target = 12;

    public ?int $editingId = null;

    public function mount(): void
    {
        $this->year = (int) now()->year;
    }

    public function saveGoal(): void
    {
        $this->validate([
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'goalType' => ['required', 'in:books,minutes'],
            'target' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $min = $this->goalType === 'books' ? 1 : 60;
        $max = $this->goalType === 'books' ? 365 : 10000;
        if ($this->target < $min || $this->target > $max) {
            $this->addError('target', __('goals.target_range', ['min' => $min, 'max' => $max]));
            return;
        }

        ReadingGoal::updateOrCreate(
            ['user_id' => auth()->id(), 'year' => $this->year, 'goal_type' => $this->goalType],
            ['target' => $this->target]
        );

        $this->checkCompletion();
        session()->flash('status', __('goals.saved'));
    }

    public function deleteGoal(int $id): void
    {
        ReadingGoal::where('user_id', auth()->id())->findOrFail($id)->delete();
        session()->flash('status', __('goals.deleted'));
    }

    /** Award goal_completed when a goal is now complete. */
    public function checkCompletion(): void
    {
        $goals = ReadingGoal::where('user_id', auth()->id())->get();
        foreach ($goals as $goal) {
            if ($goal->isComplete()) {
                app(BadgeService::class)->checkAndAward(auth()->user(), 'goal_completed');
            }
        }
    }

    public function render()
    {
        $goals = ReadingGoal::where('user_id', auth()->id())
            ->orderByDesc('year')->orderBy('goal_type')->get();

        return view('livewire.goal-tracker', compact('goals'));
    }
}
