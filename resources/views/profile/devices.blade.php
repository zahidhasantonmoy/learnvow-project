@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('devices.title') }}</h1>
  <div class="card">
  {{-- QA minor: the "N of 3 used" line was printed twice — here and in
       the Livewire device-manager. The Livewire one stays (it updates
       when a device is removed); this copy is removed. --}}
  <p>{{ __('devices.limit_note') }}</p>
  @livewire('device-manager')
  </div>
@endsection
