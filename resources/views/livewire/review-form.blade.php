<div>
  @if($submitted)
    <div class="alert">{{ __('books.review_submitted_pending') }}</div>
  @elseif(! $canReview)
    <p>{{ __('books.review_requires_entitlement') }}</p>
  @else
    <form wire:submit="submit">
      <div class="form-field">
        <label for="review-rating">{{ __('books.rating') }}</label>
        <select id="review-rating" wire:model="rating">
          @for($i = 1; $i <= 5; $i++)
            <option value="{{ $i }}">{{ $i }} ★</option>
          @endfor
        </select>
        @error('rating') <span class="error">{{ $message }}</span> @enderror
      </div>
      <div class="form-field">
        <label for="review-title">{{ __('books.review_title_label') }}</label>
        <input id="review-title" type="text" wire:model="title">
        @error('title') <span class="error">{{ $message }}</span> @enderror
      </div>
      <div class="form-field">
        <label for="review-body">{{ __('books.review_body_label') }}</label>
        <textarea id="review-body" wire:model="body" rows="4"></textarea>
        @error('body') <span class="error">{{ $message }}</span> @enderror
      </div>
      @error('review') <p class="error">{{ $message }}</p> @enderror
      <button type="submit" class="btn">{{ __('books.submit_review') }}</button>
    </form>
  @endif
</div>
