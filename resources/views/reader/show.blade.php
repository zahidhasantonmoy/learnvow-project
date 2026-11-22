{{-- Reader — EPUB via epub.js (Vite bundle, no CDN). The file URL below is the
     entitled file ROUTE, never a signed URL and never a raw path (§1.1). --}}
@extends('layouts.app')

@section('content')
<div id="reader-page"
     data-file-url="{{ route('reader.file', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}"
     data-progress-url="{{ route('reader.progress', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}"
     data-finished-url="{{ route('reader.finished', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}"
     data-bookmark-url="{{ route('reader.bookmarks.store', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}"
     data-book-slug="{{ $book->slug }}"
     data-font-family="{{ $settings->font_family }}"
     data-font-size="{{ $settings->font_size }}"
     data-line-height="{{ $settings->line_height }}"
     data-theme="{{ $settings->theme }}"
     data-brightness="{{ $settings->brightness }}"
     data-reading-mode="{{ $settings->reading_mode }}"
     data-initial-cfi="{{ $progress?->cfi ?? '' }}"
     data-initial-percent="{{ $progress?->percent ?? 0 }}"
     data-csrf="{{ csrf_token() }}">
  <h1 class="serif">{{ $book->title }}</h1>
  <p>{{ $book->author->name ?? '' }} · <span id="reader-progress-label">{{ __('reader.progress_percent', ['percent' => $progress?->percent ?? 0]) }}</span></p>

  @if($book->has_audiobook && $hasAudioFile)
    <p><a href="{{ route('audio.show', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}" class="btn">{{ __('reader.recorded_available') }}</a></p>
  @else
    <p><a href="{{ route('tts.show', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}" class="btn">{{ __('reader.tts_badge') }}</a></p>
  @endif

  <div class="reader-toolbar" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;">
    <button type="button" id="reader-toc-toggle" class="btn">{{ __('reader.toc') }}</button>
    <button type="button" id="reader-prev" class="btn">{{ __('reader.prev_page') }}</button>
    <button type="button" id="reader-next" class="btn">{{ __('reader.next_page') }}</button>
    <a href="{{ route('reader.settings', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}" class="btn">{{ __('reader.settings_title') }}</a>
    <button type="button" id="reader-add-bookmark" class="btn">{{ __('reader.add_bookmark') }}</button>
    <a href="{{ route('bookmarks.index', ['locale' => app()->getLocale()]) }}" class="btn">{{ __('reader.bookmarks') }}</a>
  </div>

  {{-- Shown on the `bookmark-saved` window event (reader.js, after a
       successful bookmark POST). Outside the Livewire component on purpose:
       the component re-renders on `bookmark-added`, and a morph would hide
       a toast living inside it. Same 5s auto-hide as reading-settings. --}}
  <p id="reader-bookmark-saved" class="alert" style="display:none;">{{ __('reader.bookmark_saved') }}</p>

  <div style="display:flex;gap:16px;align-items:flex-start;">
    <aside id="reader-toc" style="min-width:220px;max-width:260px;display:none;">
      <h2>{{ __('reader.contents') }}</h2>
      <ul id="reader-toc-list"></ul>
    </aside>
    <div style="flex:1;min-width:0;">
      <div id="reader-viewer" style="height:70vh;min-height:480px;border:1px solid var(--hatch-widget-border, #ddd);"></div>
      <div id="reader-error" class="alert" style="display:none;">
        {{ __('reader.open_failed') }} —
        <a href="{{ route('books.sample', ['locale' => app()->getLocale(), 'slug' => $book->slug]) }}">{{ __('reader.sample_link') }}</a> ·
        <a href="{{ route('pages.help', ['locale' => app()->getLocale()]) }}">{{ __('reader.contact_support') }}</a>
      </div>
    </div>
  </div>

  <div style="margin-top:16px;">
    <label for="reader-search">{{ __('reader.search_in_book') }}</label>
    <input type="search" id="reader-search" placeholder="{{ __('reader.search_placeholder') }}">
    <button type="button" id="reader-search-btn" class="btn">{{ __('reader.search_in_book') }}</button>
    <ul id="reader-search-results"></ul>
  </div>

  <div id="reader-dictionary" style="margin-top:12px;">
    <p><strong>{{ __('reader.dictionary') }}:</strong> {{ __('reader.dictionary_bn_unavailable') }}</p>
    <p id="reader-dict-result"></p>
  </div>

  @livewire('reader-view', ['book' => $book])

  @include('components.offline-state')
</div>
@endsection

@push('scripts')
@vite(['resources/js/reader.js'])
<script>
window.addEventListener('bookmark-saved', function () {
  var el = document.getElementById('reader-bookmark-saved');
  if (el) { el.style.display = 'block'; setTimeout(function () { el.style.display = 'none'; }, 5000); }
});
</script>
@endpush
