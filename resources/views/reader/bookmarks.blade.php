@extends('layouts.app')

@section('content')
<h1 class="serif">{{ __('reader.bookmarks_title') }}</h1>

@livewire('bookmark-manager')
@endsection
