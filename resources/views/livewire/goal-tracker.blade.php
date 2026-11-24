<div>
  <form wire:submit.prevent="saveGoal">
    <label>{{ __('goals.year') }}
      <input type="number" wire:model="year" min="2020" max="2100" required>
    </label>
    <label>{{ __('goals.type') }}
      <select wire:model="goalType">
        <option value="books">{{ __('goals.type_books') }}</option>
        <option value="minutes">{{ __('goals.type_minutes') }}</option>
      </select>
    </label>
    <label>{{ __('goals.target') }}
      <input type="number" wire:model="target" min="1" max="10000" required>
    </label>
    @error('target') <p>{{ $message }}</p> @enderror
    <button type="submit" class="btn">{{ __('goals.save') }}</button>
  </form>

  @foreach($goals as $goal)
    <div>
      <p><strong>{{ $goal->year }} — {{ __('goals.type_'.$goal->goal_type) }}</strong></p>
      <p>{{ __('goals.progress_text', ['done' => $goal->progress(), 'target' => $goal->target, 'percent' => $goal->percent()]) }}</p>
      <progress value="{{ $goal->progress() }}" max="{{ $goal->target }}"></progress>
      @if($goal->isComplete())
        <span class="badge">{{ __('goals.completed') }}</span>
      @endif
      <button type="button" wire:click="deleteGoal({{ $goal->id }})">{{ __('goals.delete') }}</button>
    </div>
  @endforeach
</div>
