<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:16px">
  <div class="form-field" style="margin:0">
    <label for="f-category">{{ __('books.category') }}</label>
    <select id="f-category" wire:model.live="categorySlug">
      <option value="">{{ __('books.all_categories') }}</option>
      @foreach($categories as $category)
        <option value="{{ $category->slug }}">{{ $category->displayName() }}</option>
      @endforeach
    </select>
  </div>
  <div class="form-field" style="margin:0">
    <label for="f-price">{{ __('books.price') }}</label>
    <select id="f-price" wire:model.live="price">
      <option value="">{{ __('books.all_prices') }}</option>
      <option value="free">{{ __('books.free_only') }}</option>
      <option value="paid">{{ __('books.paid_only') }}</option>
    </select>
  </div>
  <div class="form-field" style="margin:0">
    <label for="f-language">{{ __('books.language') }}</label>
    <select id="f-language" wire:model.live="language">
      <option value="">{{ __('books.all_languages') }}</option>
      <option value="bn">{{ __('books.bangla') }}</option>
      <option value="en">{{ __('books.english') }}</option>
    </select>
  </div>
  <div class="form-field" style="margin:0">
    <label for="f-sort">{{ __('books.sort') }}</label>
    <select id="f-sort" wire:model.live="sort">
      <option value="newest">{{ __('books.sort_newest') }}</option>
      <option value="popular">{{ __('books.sort_popular') }}</option>
      <option value="price_asc">{{ __('books.sort_price_asc') }}</option>
      <option value="price_desc">{{ __('books.sort_price_desc') }}</option>
      <option value="rating">{{ __('books.sort_rating') }}</option>
    </select>
  </div>
</div>
