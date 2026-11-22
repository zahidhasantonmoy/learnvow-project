// LearnVow reader — epub.js (npm bundle, no CDN). Spec §1.
// The EPUB is opened through the entitled file ROUTE (data-file-url);
// that route redirects to a 15-minute signed URL, so no signed URL
// and no raw file path ever appears in this code or the page.
import ePub from 'epubjs';

function applySettings(rendition, settings) {
  if (!rendition || !settings) return;
  const family = settings.fontFamily === 'sans' ? 'sans-serif' : 'serif';
  rendition.themes.fontSize(`${settings.fontSize}%`);
  rendition.themes.font(family);
  rendition.themes.override('line-height', String(settings.lineHeight));
  const themes = {
    paper: { body: { background: '#fffdf5', color: '#1a1a1a' } },
    sepia: { body: { background: '#f4ecd8', color: '#433422' } },
    night: { body: { background: '#121212', color: '#e8e8e8' } },
  };
  rendition.themes.register('reader-theme', themes[settings.theme] || themes.paper);
  rendition.themes.select('reader-theme');
  const viewer = document.getElementById('reader-viewer');
  if (viewer) viewer.style.filter = `brightness(${settings.brightness / 100})`;
}

function readSettings(page) {
  return {
    fontFamily: page.dataset.fontFamily || 'serif',
    fontSize: parseInt(page.dataset.fontSize || '100', 10),
    lineHeight: parseFloat(page.dataset.lineHeight || '1.6'),
    theme: page.dataset.theme || 'paper',
    brightness: parseInt(page.dataset.brightness || '100', 10),
    readingMode: page.dataset.readingMode || 'paged',
  };
}

function initReader(page) {
  // epub.js's rendition manager registers a window "unload" listener when
  // renderTo() runs, and Chrome logs "Permissions policy violation: unload
  // is not allowed in this document" for that registration on every reader
  // load. That listener only calls destroy() as the page unloads, so
  // dropping the registration is harmless — swallow "unload" listeners on
  // this window and let everything else through unchanged.
  const nativeAddEventListener = window.addEventListener.bind(window);
  window.addEventListener = function (type, listener, options) {
    if (type === 'unload') return;
    return nativeAddEventListener(type, listener, options);
  };

  const viewer = document.getElementById('reader-viewer');
  const errorBox = document.getElementById('reader-error');
  const settings = readSettings(page);
  let book;
  let rendition;
  let currentCfi = page.dataset.initialCfi || '';
  let currentPercent = parseInt(page.dataset.initialPercent || '0', 10);
  let chapterIndex = 0;
  let startedAt = Date.now();
  let saveTimer = null;

  const queueKey = `lv-progress-${page.dataset.bookSlug}`;

  function queuedMinutes() {
    return Math.floor((Date.now() - startedAt) / 60000);
  }

  function saveProgress(force) {
    const payload = {
      cfi: currentCfi,
      percent: currentPercent,
      chapter_index: chapterIndex,
      total_minutes: queuedMinutes(),
      last_read_at: new Date().toISOString(),
    };
    if (!navigator.onLine) {
      try { localStorage.setItem(queueKey, JSON.stringify(payload)); } catch (e) {}
      return;
    }
    fetch(page.dataset.progressUrl, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': page.dataset.csrf, Accept: 'application/json' },
      body: JSON.stringify(payload),
    })
      .then((r) => r.json())
      .then((data) => {
        if (data.finished && page.dataset.finishedUrl) {
          window.location.href = page.dataset.finishedUrl;
        }
      })
      .catch(() => {
        try { localStorage.setItem(queueKey, JSON.stringify(payload)); } catch (e) {}
      });
  }

  function scheduleSave() {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => saveProgress(false), 500);
  }

  function flushQueue() {
    let queued = null;
    try { queued = JSON.parse(localStorage.getItem(queueKey) || 'null'); } catch (e) {}
    if (!queued || !navigator.onLine) return;
    fetch(page.dataset.progressUrl, {
      method: 'PUT',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': page.dataset.csrf, Accept: 'application/json' },
      body: JSON.stringify(queued),
    }).then(() => localStorage.removeItem(queueKey)).catch(() => {});
  }

  window.addEventListener('online', flushQueue);
  setInterval(() => saveProgress(false), 30000);
  flushQueue();

  // The file-route URL has no .epub extension, so epub.js would treat it
  // as an UNPACKED epub directory and request META-INF/container.xml (404)
  // instead of fetching the archive. Fetch the entitled file route as bytes
  // instead — the fetch follows the 302 to the 15-minute signed URL, and
  // canRead() still gates access because the request goes through the route
  // (no signed URL and no raw file path ever appears here) — then open the
  // archive from memory with ePub(arrayBuffer).
  function openBook() {
    return fetch(page.dataset.fileUrl, { credentials: 'same-origin' })
      .then((res) => {
        if (!res.ok) throw new Error(`EPUB fetch failed: ${res.status}`);
        return res.arrayBuffer();
      })
      .then((buffer) => ePub(buffer));
  }

  function showError() {
    if (errorBox) errorBox.style.display = 'block';
  }

  openBook()
    .then((opened) => {
      book = opened;
      // C1-residual fix: the container now has a definite height (70vh in
      // the Blade view), but pass an explicit pixel height too — a
      // percentage height resolved to 0 against the old min-height-only
      // container and left the rendition iframe at height 0px.
      rendition = book.renderTo(viewer, {
        width: '100%',
        height: viewer.clientHeight || 600,
        flow: settings.readingMode === 'scroll' ? 'scrolled' : 'paginated',
        manager: settings.readingMode === 'scroll' ? 'continuous' : 'default',
      });
      applySettings(rendition, settings);

      // Re-measure after load and on window resize so the iframe keeps a
      // real height when the viewport changes.
      const resizeRendition = () => {
        if (rendition && viewer.clientHeight > 0) rendition.resize(viewer.clientWidth, viewer.clientHeight);
      };
      window.addEventListener('resize', resizeRendition);

      rendition.on('relocated', (location) => {
        currentCfi = location.start.cfi;
        currentPercent = Math.round((location.start.percentage || 0) * 100);
        const label = document.getElementById('reader-progress-label');
        if (label) label.textContent = `${currentPercent}%`;
        scheduleSave();
      });

      return book.ready
        .then(() => book.locations.generate(1024))
        .then(() => {
          const tocList = document.getElementById('reader-toc-list');
          return book.loaded.navigation.then((nav) => {
            nav.toc.forEach((item, i) => {
              const li = document.createElement('li');
              const a = document.createElement('a');
              a.href = '#';
              a.textContent = item.label;
              a.addEventListener('click', (e) => { e.preventDefault(); rendition.display(item.href); chapterIndex = i; });
              li.appendChild(a);
              tocList?.appendChild(li);
            });
          });
        })
        .then(() => (currentCfi ? rendition.display(currentCfi) : rendition.display()))
        .then(() => rendition.resize(viewer.clientWidth, viewer.clientHeight));
    })
    .catch(showError);

  // UI wiring — guarded so clicks before the book has opened are no-ops
  // instead of throwing (previously prev/next threw on undefined rendition
  // and bookmark posted an empty CFI → 422 while the book never opened).
  try {
    document.getElementById('reader-prev')?.addEventListener('click', () => rendition?.prev());
    document.getElementById('reader-next')?.addEventListener('click', () => rendition?.next());
    document.getElementById('reader-toc-toggle')?.addEventListener('click', () => {
      const toc = document.getElementById('reader-toc');
      if (toc) toc.style.display = toc.style.display === 'none' ? 'block' : 'none';
    });
    document.getElementById('reader-add-bookmark')?.addEventListener('click', () => {
      if (!currentCfi) return; // no location yet — nothing valid to bookmark
      fetch(page.dataset.bookmarkUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': page.dataset.csrf, Accept: 'application/json' },
        body: JSON.stringify({ cfi: currentCfi }),
      }).then((r) => {
        if (!r.ok) return;
        // Visible confirmation: same `bookmark-saved` window event the
        // Livewire component dispatches (show.blade.php toasts on it), and
        // refresh the on-page bookmark list via the Livewire component.
        window.dispatchEvent(new CustomEvent('bookmark-saved'));
        if (window.Livewire) window.Livewire.dispatch('bookmark-added');
      }).catch(() => {});
    });

    // In-book search: epub.js spine search, client-side (§1.5).
    document.getElementById('reader-search-btn')?.addEventListener('click', async () => {
      const q = document.getElementById('reader-search')?.value.trim();
      const resultsEl = document.getElementById('reader-search-results');
      if (!q || !resultsEl || !book) return; // no book open yet — nothing to search
      resultsEl.innerHTML = '';
      try {
        const results = [];
        for (const item of book.spine.spineItems) {
          // Section.load() resolves to the contents element, NOT the section —
          // find() lives on the section itself (it needs section.document),
          // so load the item, then call find() on the item.
          await item.load(book.load.bind(book));
          const found = item.find(q);
          found.forEach((f) => results.push(f));
          // NOTE: do NOT call item.unload() here — epub.js's unload
          // triggers "Permissions policy violation: unload is not allowed
          // in this document" on every search. Spine items stay loaded
          // for the search loop; the book is small and results cap at 20.
          if (results.length >= 20) break;
        }
        if (!results.length) {
          resultsEl.innerHTML = '<li>—</li>';
          return;
        }
        results.slice(0, 20).forEach((f) => {
          const li = document.createElement('li');
          const a = document.createElement('a');
          a.href = '#';
          a.textContent = (f.excerpt || q).slice(0, 120);
          a.addEventListener('click', (e) => { e.preventDefault(); rendition.display(f.cfi); });
          li.appendChild(a);
          resultsEl.appendChild(li);
        });
      } catch (e) { /* search failure is silent — never a broken UI */ }
    });

    // Dictionary: English via dictionaryapi.dev, client-side, fails silently (§1.5).
    document.addEventListener('mouseup', () => {
      const sel = window.getSelection()?.toString().trim();
      const out = document.getElementById('reader-dict-result');
      if (!sel || !/^[A-Za-z]+$/.test(sel) || !out) return;
      fetch(`https://api.dictionaryapi.dev/api/v2/entries/en/${encodeURIComponent(sel.toLowerCase())}`)
        .then((r) => (r.ok ? r.json() : null))
        .then((data) => {
          const meaning = data?.[0]?.meanings?.[0]?.definitions?.[0]?.definition;
          out.textContent = meaning ? `${sel}: ${meaning}` : '';
        })
        .catch(() => { out.textContent = ''; });
    });

    // Livewire settings changes apply to the open rendition immediately.
    window.addEventListener('settings-saved', (e) => {
      const s = e.detail?.settings || e.detail?.[0]?.settings;
      if (s && rendition) {
        applySettings(rendition, {
          fontFamily: s.font_family,
          fontSize: s.font_size,
          lineHeight: s.line_height,
          theme: s.theme,
          brightness: s.brightness,
          readingMode: s.reading_mode,
        });
      }
    });
  } catch (e) {
    if (errorBox) errorBox.style.display = 'block';
  }
}

function initTts(page) {
  const unsupported = document.getElementById('tts-unsupported');
  if (!('speechSynthesis' in window)) {
    if (unsupported) unsupported.style.display = 'block';
    return;
  }
  const textEl = document.getElementById('tts-text');
  const voiceSel = document.getElementById('tts-voice');
  const rateSel = document.getElementById('tts-rate');
  const bookLang = page.dataset.bookLang === 'bn' ? 'bn' : 'en';
  let chapterText = '';
  let utterance = null;

  // Same fix as the reader: fetch the entitled file route as bytes so
  // epub.js opens the archive instead of treating the route as an
  // unpacked EPUB directory (META-INF/container.xml 404).
  fetch(page.dataset.fileUrl, { credentials: 'same-origin' })
    .then((res) => {
      if (!res.ok) throw new Error(`EPUB fetch failed: ${res.status}`);
      return res.arrayBuffer();
    })
    .then((buffer) => ePub(buffer))
    .then((book) => book.ready.then(() => {
      // Section.load() resolves to the contents element, NOT the section —
      // the parsed document lives on the spine item itself.
      const first = book.spine.get(0);
      return first?.load(book.load.bind(book)).then(() => {
        chapterText = first.document?.querySelector('body')?.textContent
          || first.contents?.textContent
          || '';
        if (textEl) textEl.textContent = chapterText;
      });
    }))
    .catch(() => {});

  function voices() {
    return window.speechSynthesis.getVoices();
  }
  function fillVoices() {
    if (!voiceSel) return;
    voiceSel.innerHTML = '';
    voices().forEach((v) => {
      const o = document.createElement('option');
      o.value = v.name;
      o.textContent = `${v.name} (${v.lang})`;
      voiceSel.appendChild(o);
    });
    const preferred = voices().find((v) => v.lang.toLowerCase().startsWith(bookLang));
    if (preferred) voiceSel.value = preferred.name;
    else if (bookLang === 'bn') {
      const warn = document.getElementById('tts-no-bn-voice');
      if (warn) warn.style.display = 'block';
    }
  }
  fillVoices();
  window.speechSynthesis.onvoiceschanged = fillVoices;

  function speak() {
    if (!chapterText) return;
    window.speechSynthesis.cancel();
    utterance = new SpeechSynthesisUtterance(chapterText);
    utterance.rate = parseFloat(rateSel?.value || '1');
    const voice = voices().find((v) => v.name === voiceSel?.value)
      || voices().find((v) => v.lang.toLowerCase().startsWith(bookLang));
    if (voice) utterance.voice = voice;
    utterance.lang = bookLang === 'bn' ? 'bn-BD' : 'en-US';
    window.speechSynthesis.speak(utterance);
  }

  document.getElementById('tts-play')?.addEventListener('click', speak);
  document.getElementById('tts-pause')?.addEventListener('click', () => window.speechSynthesis.pause());
  document.getElementById('tts-stop')?.addEventListener('click', () => window.speechSynthesis.cancel());
  rateSel?.addEventListener('change', () => { if (utterance) utterance.rate = parseFloat(rateSel.value); });
}

const readerPage = document.getElementById('reader-page');
if (readerPage) initReader(readerPage);
const ttsPage = document.getElementById('tts-page');
if (ttsPage) initTts(ttsPage);
