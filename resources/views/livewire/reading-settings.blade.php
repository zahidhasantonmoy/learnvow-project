<div>
  <div>
    <label>{{ __('reader.font_family') }}</label>
    <select wire:model.live.debounce.500ms="fontFamily">
      <option value="serif">{{ __('reader.font_serif') }}</option>
      <option value="sans">{{ __('reader.font_sans') }}</option>
    </select>
  </div>
  <div>
    <label>{{ __('reader.font_size') }}: {{ $fontSize }}%</label>
    <input type="range" min="80" max="200" step="5" wire:model.live.debounce.500ms="fontSize">
  </div>
  <div>
    <label>{{ __('reader.line_height') }}: {{ $lineHeight }}</label>
    <input type="range" min="1.4" max="2.2" step="0.05" wire:model.live.debounce.500ms="lineHeight">
  </div>
  <div>
    <label>{{ __('reader.theme') }}</label>
    <select wire:model.live.debounce.500ms="theme">
      <option value="paper">{{ __('reader.theme_paper') }}</option>
      <option value="sepia">{{ __('reader.theme_sepia') }}</option>
      <option value="night">{{ __('reader.theme_night') }}</option>
    </select>
  </div>
  <div>
    <label>{{ __('reader.brightness') }}: {{ $brightness }}%</label>
    <input type="range" min="50" max="100" step="5" wire:model.live.debounce.500ms="brightness">
  </div>
  <div>
    <label>{{ __('reader.reading_mode') }}</label>
    <select wire:model.live.debounce.500ms="readingMode">
      <option value="paged">{{ __('reader.mode_paged') }}</option>
      <option value="scroll">{{ __('reader.mode_scroll') }}</option>
    </select>
  </div>
  <p id="reading-settings-failed" class="alert" style="display:none;">{{ __('reader.settings_save_failed') }}</p>
  <script>
  window.addEventListener('settings-save-failed', function () {
    var el = document.getElementById('reading-settings-failed');
    if (el) { el.style.display = 'block'; setTimeout(function () { el.style.display = 'none'; }, 5000); }
  });
  window.addEventListener('settings-saved', function () {
    var el = document.getElementById('reading-settings-failed');
    if (el) el.style.display = 'none';
  });
  </script>
</div>
