<div>
  @if($error)<p class="alert">{{ $error }}</p>@endif
  @if($notice)<p class="alert">{{ $notice }}</p>@endif
  <form wire:submit.prevent="save" novalidate>
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
    <p><button type="submit" class="btn">{{ __('author.save') }}</button></p>
  </form>
  <p>{{ __('author.price_note') }}</p>
  <p>
    @if($book->is_paused)
      <button type="button" class="btn" wire:click="resume">{{ __('author.resume') }}</button>
    @else
      <button type="button" class="btn btn-secondary" wire:click="pause" wire:confirm="{{ __('author.pause') }}?">{{ __('author.pause') }}</button>
    @endif
  </p>
</div>
