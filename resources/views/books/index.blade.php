@extends('layouts.app')

@section('content')
  <h1 class="serif">
    @isset($category)
      {{ $category->displayName() }}
    @elseif(!empty($isFreePage))
      {{ __('books.free_books') }}
    @else
      {{ __('books.browse_title') }}
    @endisset
  </h1>
  @isset($category)
    @if($category->displayDescription())
      <p>{{ $category->displayDescription() }}</p>
    @endif
  @endisset

  @livewire('book-filters', ['filters' => $filters ?? []])
  @livewire('book-list', ['filters' => $filters ?? []])
@endsection
