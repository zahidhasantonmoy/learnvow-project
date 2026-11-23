<div>
  @if($uploaded)<p class="alert">{{ __('author.audio_success') }}</p>@endif
  @if($error)<p class="alert">{{ $error }}</p>@endif
  @if($book->has_audiobook)
    <p>{{ __('author.audio_existing') }}</p>
  @else
    <p>{{ __('author.audio_coming_soon') }}</p>
  @endif
  <form wire:submit.prevent="submit" novalidate>
    <p>
      <label for="audio">{{ __('author.audio_file') }} *</label><br>
      <input type="file" id="audio" wire:model="audio" accept="audio/*" required>
      <small>{{ __('author.audio_hint') }}</small>
      @error('audio')<br><span>{{ $message }}</span>@enderror
    </p>
    <p><button type="submit" class="btn">{{ __('author.submit') }}</button></p>
  </form>
</div>
