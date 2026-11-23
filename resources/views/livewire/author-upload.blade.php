<div>
  <p><strong>{{ __('author.publish_share_note') }}</strong></p>
  @if($error)<p class="alert">{{ $error }}</p>@endif
  <form wire:submit.prevent="submit" novalidate>
    <p>
      <label for="title">{{ __('author.title') }} *</label><br>
      <input type="text" id="title" wire:model="title" maxlength="255" required>
      @error('title')<br><span>{{ $message }}</span>@enderror
    </p>
    <p>
      <label for="titleEn">{{ __('author.title_en') }} ({{ __('author.optional') }})</label><br>
      <input type="text" id="titleEn" wire:model="titleEn" maxlength="255">
    </p>
    <p>
      <label for="description">{{ __('author.description') }} *</label><br>
      <textarea id="description" wire:model="description" rows="5" maxlength="10000" required></textarea>
      @error('description')<br><span>{{ $message }}</span>@enderror
    </p>
    <p>
      <label for="descriptionEn">{{ __('author.description_en') }} ({{ __('author.optional') }})</label><br>
      <textarea id="descriptionEn" wire:model="descriptionEn" rows="5" maxlength="10000"></textarea>
    </p>
    <p>
      <label for="language">{{ __('author.language') }} *</label><br>
      <select id="language" wire:model="language">
        <option value="bn">{{ __('author.bangla') }}</option>
        <option value="en">{{ __('author.english') }}</option>
      </select>
    </p>
    <p>
      <label for="categoryId">{{ __('author.category') }} *</label><br>
      <select id="categoryId" wire:model="categoryId" required>
        <option value="">—</option>
        @foreach($categories as $category)
          <option value="{{ $category->id }}">{{ $category->displayName() }}</option>
        @endforeach
      </select>
      @error('categoryId')<br><span>{{ $message }}</span>@enderror
    </p>
    <p>
      <label for="priceBdt">{{ __('author.price_bdt') }} *</label><br>
      <input type="number" id="priceBdt" wire:model="priceBdt" min="0" required>
      @error('priceBdt')<br><span>{{ $message }}</span>@enderror
    </p>
    <p>
      <label for="listPriceBdt">{{ __('author.list_price_bdt') }} ({{ __('author.optional') }})</label><br>
      <input type="number" id="listPriceBdt" wire:model="listPriceBdt" min="0">
    </p>
    <p>
      <label for="isbn">{{ __('author.isbn') }} ({{ __('author.optional') }})</label><br>
      <input type="text" id="isbn" wire:model="isbn" maxlength="20">
      <small>{{ __('author.isbn_hint') }}</small>
    </p>
    <p>
      <label for="pages">{{ __('author.pages') }} ({{ __('author.optional') }})</label><br>
      <input type="number" id="pages" wire:model="pages" min="1">
    </p>
    <p>
      <label for="samplePercent">{{ __('author.sample_percent') }}</label><br>
      <input type="number" id="samplePercent" wire:model="samplePercent" min="0" max="100">
    </p>
    <p>
      <label><input type="checkbox" wire:model="isSubscriptionIncluded"> {{ __('author.is_subscription_included') }}</label><br>
      <label><input type="checkbox" wire:model="allowPod"> {{ __('author.allow_pod') }}</label><br>
      <label><input type="checkbox" wire:model="allowLicensedPdf"> {{ __('author.allow_licensed_pdf') }}</label>
    </p>
    <p>
      <label for="printPriceBdt">{{ __('author.print_price_bdt') }} ({{ __('author.optional') }})</label><br>
      <input type="number" id="printPriceBdt" wire:model="printPriceBdt" min="0">
    </p>
    <p>
      <label for="bookFile">{{ __('author.book_file') }} *</label><br>
      <input type="file" id="bookFile" wire:model="bookFile" accept=".epub,.pdf" required>
      <small>{{ __('author.book_file_hint') }}</small>
      @error('bookFile')<br><span>{{ $message }}</span>@enderror
    </p>
    <p>
      <label for="cover">{{ __('author.cover') }} ({{ __('author.optional') }})</label><br>
      <input type="file" id="cover" wire:model="cover" accept="image/*">
      <small>{{ __('author.cover_hint') }}</small>
      @error('cover')<br><span>{{ $message }}</span>@enderror
    </p>
    <p><button type="submit" class="btn">{{ __('author.publish_submit') }}</button></p>
  </form>
</div>
