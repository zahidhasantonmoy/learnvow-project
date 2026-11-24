@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ $episode->title }}</h1>
  <p><a href="{{ route('serials.show', ['locale' => app()->getLocale(), 'serial' => $serial->id]) }}">{{ $serial->title }}</a></p>
  <div>{!! nl2br(e($episode->content)) !!}</div>
@endsection
