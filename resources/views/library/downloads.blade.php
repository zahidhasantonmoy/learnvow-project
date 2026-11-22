@extends('layouts.app')

@section('content')
<h1 class="serif">{{ __('reader.downloads_heading') }}</h1>

@include('components.offline-state')

@if($downloads->isEmpty())
  <p>{{ __('reader.no_downloads') }}</p>
@else
  <table>
    <thead>
      <tr>
        <th>{{ __('reader.title') }}</th>
        <th>{{ __('reader.download_size') }}</th>
        <th>{{ __('reader.downloaded_at') }}</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      @foreach($downloads as $download)
        <tr>
          <td>{{ $download->book->title ?? '' }}</td>
          <td>{{ $download->size_bytes !== null ? number_format($download->size_bytes / 1048576, 2).' MB' : '—' }}</td>
          <td>{{ $download->downloaded_at?->format('Y-m-d H:i') }}</td>
          <td>
            @if($download->book)
              <a href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $download->book->slug]) }}" class="btn">{{ __('reader.read_offline') }}</a>
            @endif
            <form method="POST" action="{{ route('downloads.destroy', ['locale' => app()->getLocale(), 'download' => $download->id]) }}" style="display:inline">
              @csrf @method('DELETE')
              <button type="submit" class="btn">{{ __('reader.delete') }}</button>
            </form>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
  {{ $downloads->links() }}
@endif
@endsection
