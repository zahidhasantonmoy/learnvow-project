<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Yearly reading goal (§4.4). One active goal per user+year+type.
 * Progress is computed live — books finished that year, or minutes
 * from the reading_minutes ledger (same source as the pool, §4.4).
 */
class ReadingGoal extends Model
{
    protected $fillable = ['user_id', 'year', 'goal_type', 'target'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'target' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Live progress: finished books, or minutes read, in the goal year. */
    public function progress(): int
    {
        if ($this->goal_type === 'minutes') {
            return (int) \DB::table('reading_minutes')
                ->where('user_id', $this->user_id)
                ->where('period', 'like', $this->year.'-%')
                ->sum('minutes');
        }

        return (int) ReadingProgress::where('user_id', $this->user_id)
            ->whereNotNull('finished_at')
            ->whereYear('finished_at', $this->year)
            ->count();
    }

    public function percent(): int
    {
        if ($this->target <= 0) {
            return 0;
        }

        return (int) min(100, floor($this->progress() / $this->target * 100));
    }

    public function isComplete(): bool
    {
        return $this->progress() >= $this->target;
    }
}
