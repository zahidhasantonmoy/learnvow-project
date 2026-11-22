<div>
  <div class="form-field">
    <input type="search" wire:model.live.debounce.300ms="query"
      placeholder="{{ __('search.placeholder') }}" aria-label="{{ __('search.title') }}">
  </div>
  @if($searched)
    @if($results->isEmpty())
      <p>{{ __('search.no_results', ['query' => $query]) }}</p>
    @else
      <p>{{ __('search.results_count', ['count' => $results->count()]) }}</p>
      <div class="book-grid">
        @foreach($results as $book)
          <x-book-card :book="$book" />
        @endforeach
      </div>
    @endif
  @endif
</div>
