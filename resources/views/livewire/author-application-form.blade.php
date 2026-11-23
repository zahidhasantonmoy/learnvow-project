<div>
  @if($submitted)
    <p class="alert">{{ __('author.apply_success') }}</p>
    <p>{{ __('author.apply_pending') }}</p>
  @else
    @if($error)<p class="alert">{{ $error }}</p>@endif
    <form wire:submit.prevent="submit" novalidate>
      <p>
        <label for="penName">{{ __('author.pen_name') }} ({{ __('author.optional') }})</label><br>
        <input type="text" id="penName" wire:model="penName" maxlength="100">
        <small>{{ __('author.pen_name_hint') }}</small>
      </p>
      <p>
        <label for="bio">{{ __('author.bio') }}</label><br>
        <textarea id="bio" wire:model="bio" rows="4" maxlength="2000"></textarea>
      </p>
      <p>
        <label for="bioEn">{{ __('author.bio_en') }} ({{ __('author.optional') }})</label><br>
        <textarea id="bioEn" wire:model="bioEn" rows="4" maxlength="2000"></textarea>
      </p>
      <p>
        <label for="bkashNumber">{{ __('author.bkash_number') }} *</label><br>
        <input type="text" id="bkashNumber" wire:model="bkashNumber" maxlength="20" required>
        <small>{{ __('author.bkash_hint') }}</small>
        @error('bkashNumber')<br><span>{{ $message }}</span>@enderror
      </p>
      <p>
        <label for="document">{{ __('author.id_document') }} *</label><br>
        <input type="file" id="document" wire:model="document" accept=".pdf,.jpg,.jpeg,.png" required>
        <small>{{ __('author.id_document_hint') }}</small>
        @error('document')<br><span>{{ $message }}</span>@enderror
      </p>
      <p><button type="submit" class="btn">{{ __('author.apply_submit') }}</button></p>
    </form>
  @endif
</div>
