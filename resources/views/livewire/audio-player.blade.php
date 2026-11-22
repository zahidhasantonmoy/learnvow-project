<div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin:12px 0;">
    <button type="button" wire:click="cycleSpeed" class="btn">{{ __('reader.speed') }}: {{ $speed }}×</button>
    <button type="button" id="audio-skip-back" class="btn">{{ __('reader.skip_back') }}</button>
    <button type="button" id="audio-skip-forward" class="btn">{{ __('reader.skip_forward') }}</button>
    <label>{{ __('reader.sleep_timer') }}
      <select wire:model.live="sleepMinutes">
        <option value="">{{ __('reader.sleep_off') }}</option>
        <option value="15">15</option>
        <option value="30">30</option>
        <option value="60">60</option>
      </select>
    </label>
  </div>
  <script>
  (function () {
    var audio = document.getElementById('audiobook-player');
    if (!audio) return;
    document.getElementById('audio-skip-back')?.addEventListener('click', function () { audio.currentTime = Math.max(0, audio.currentTime - 10); });
    document.getElementById('audio-skip-forward')?.addEventListener('click', function () { audio.currentTime = Math.min(audio.duration || 0, audio.currentTime + 10); });
    // Keep the <audio> element's speed in sync with the Livewire state.
    audio.playbackRate = parseFloat(@json($speed));
  })();
  </script>
</div>
