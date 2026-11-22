{{-- Offline state (spec §1.7): plain notice, no fake content. Shown when the
     browser reports no connection; downloaded books still open from the
     service-worker's cache (resources/js/reader.js registers /sw.js). --}}
<div id="offline-state" class="alert" style="display:none;">
  <strong>{{ __('reader.offline_title') }}</strong>
  <p>{{ __('reader.offline_text') }}</p>
</div>
<div id="back-online-state" class="alert" style="display:none;">
  {{ __('reader.back_online') }}
</div>
<script>
(function () {
  function update() {
    var off = document.getElementById('offline-state');
    var on = document.getElementById('back-online-state');
    if (!off) return;
    if (navigator.onLine) { off.style.display = 'none'; if (on) { on.style.display = 'block'; setTimeout(function () { on.style.display = 'none'; }, 5000); } }
    else { off.style.display = 'block'; if (on) on.style.display = 'none'; }
  }
  window.addEventListener('online', update);
  window.addEventListener('offline', update);
  update();
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/sw.js').catch(function () {});
  }
})();
</script>
