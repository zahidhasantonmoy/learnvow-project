@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.preorders_title') }}</h1>
  <p>{{ __('books.preorders_intro') }}</p>
  @if($preorders->isEmpty())
    <p>{{ __('books.preorders_empty') }}</p>
  @else
    <ul>
      @foreach($preorders as $preorder)
        <li>
          <strong>{{ $preorder->book->title }}</strong>
          @if($preorder->book->preorder_release_at) — {{ $preorder->book->preorder_release_at->toDateString() }} @endif
          <span class="badge">{{ __('books.preorder_status_'.$preorder->status) }}</span>
          @if(in_array($preorder->status, ['reserved', 'notified'], true))
            <form method="POST" action="{{ route('preorders.destroy', ['locale' => app()->getLocale(), 'preorder' => $preorder->id]) }}" style="display:inline">
              @csrf
              @method('DELETE')
              <button type="submit">{{ __('books.preorder_cancel') }}</button>
            </form>
          @endif
        </li>
      @endforeach
    </ul>
  @endif
@endsection
