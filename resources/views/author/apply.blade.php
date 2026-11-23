@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('author.apply_heading') }}</h1>
  <p>{{ __('author.apply_intro') }}</p>

  @if($application)
    <p class="alert">{{ __('author.apply_pending') }}</p>
    <dl>
      <dt>{{ __('author.status') }}</dt>
      <dd>{{ $application->status }}</dd>
      @if($application->review_note)
        <dt>{{ __('author.apply_rejected_reason') }}</dt>
        <dd>{{ $application->review_note }}</dd>
      @endif
    </dl>
  @else
    @livewire('author-application-form')
  @endif
@endsection
