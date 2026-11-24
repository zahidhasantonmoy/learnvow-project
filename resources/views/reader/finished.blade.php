@extends('layouts.app')

@section('content')
<h1 class="serif">{{ __('reader.finished_heading') }}</h1>
<p>{{ __('reader.finished_text', ['title' => $book->title]) }}</p>
<p><span class="badge">{{ __('reader.finished_badge') }}</span>@if($progress->total_minutes > 0) · {{ __('reader.minutes_read', ['minutes' => $progress->total_minutes]) }}@endif</p>

<div style="display:flex;gap:8px;flex-wrap:wrap;margin:16px 0;">
  <a href="{{ route('books.show', ['locale' => app()->getLocale(), 'slug' => $book->slug]) }}" class="btn">{{ __('reader.rate_book') }}</a>
  <a href="{{ route('books.show', ['locale' => app()->getLocale(), 'slug' => $book->slug]) }}" class="btn">{{ __('reader.write_review') }}</a>
  @if($nextBook)
    <a href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $nextBook->slug]) }}" class="btn">{{ __('reader.next_book') }}: {{ $nextBook->title }}</a>
  @endif
  <button type="button" id="finished-share" class="btn">{{ __('reader.share') }}</button>
  <a href="{{ route('library.index', ['locale' => app()->getLocale()]) }}" class="btn">{{ __('reader.back_to_library') }}</a>
</div>

@push('scripts')
<script>
document.getElementById('finished-share')?.addEventListener('click', function () {
  var data = { title: @json($book->title), text: @json(__('reader.finished_text', ['title' => $book->title])), url: @json(route('books.show', ['locale' => app()->getLocale(), 'slug' => $book->slug])) };
  if (navigator.share) { navigator.share(data).catch(function () {}); }
  else if (navigator.clipboard) { navigator.clipboard.writeText(data.url); }
});
</script>
@endpush
@endsection
