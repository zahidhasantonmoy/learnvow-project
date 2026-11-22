{{-- TTS page: chapter text is loaded from the EPUB in the browser and read
     aloud with the Web Speech API — no server cost, no audio file (§1.6). --}}
@extends('layouts.app')

@section('content')
<div id="tts-page"
     data-file-url="{{ route('reader.file', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}"
     data-book-lang="{{ $book->language }}">
  <h1 class="serif">{{ __('reader.tts_heading') }} — {{ $book->title }}</h1>
  <p>{{ __('reader.tts_text') }}</p>

  <div id="tts-unsupported" class="alert" style="display:none;">{{ __('reader.tts_unsupported') }}</div>
  <div id="tts-no-bn-voice" class="alert" style="display:none;">{{ __('reader.tts_no_bn_voice') }}</div>

  <div style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;align-items:center;">
    <button type="button" id="tts-play" class="btn">{{ __('reader.tts_play') }}</button>
    <button type="button" id="tts-pause" class="btn">{{ __('reader.tts_pause') }}</button>
    <button type="button" id="tts-stop" class="btn">{{ __('reader.tts_stop') }}</button>
    <label>{{ __('reader.tts_speed') }}
      <select id="tts-rate">
        <option value="0.75">0.75×</option>
        <option value="1" selected>1.0×</option>
        <option value="1.25">1.25×</option>
        <option value="1.5">1.5×</option>
        <option value="2">2.0×</option>
      </select>
    </label>
    <label>{{ __('reader.tts_voice') }} <select id="tts-voice"></select></label>
  </div>

  <h2>{{ __('reader.tts_chapter_text') }}</h2>
  <div id="tts-text" style="white-space:pre-wrap;"></div>

  <p><a href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}" class="btn">{{ __('reader.back_to_book') }}</a></p>
</div>
@endsection

@push('scripts')
@vite(['resources/js/reader.js'])
@endpush
