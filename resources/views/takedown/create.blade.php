@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('moderation.takedowns.file_claim') }}</h1>
  <p>{{ __('moderation.takedowns.deadline') }} — {{ __('moderation.takedowns.claim_note') }}</p>

  <form method="POST" action="{{ route('takedown.store', ['locale' => app()->getLocale()]) }}" enctype="multipart/form-data">
    @csrf
    <div>
      <label>{{ __('moderation.takedowns.book') }}
        <select name="book_id" required>
          <option value="">—</option>
          @foreach($books as $book)
            <option value="{{ $book->id }}" @selected(old('book_id') == $book->id)>{{ $book->title }}</option>
          @endforeach
        </select>
      </label>
      @error('book_id')<p>{{ $message }}</p>@enderror
    </div>
    <div>
      <label>{{ __('moderation.takedowns.claimant_name') }}
        <input type="text" name="claimant_name" value="{{ old('claimant_name') }}" required maxlength="255">
      </label>
      @error('claimant_name')<p>{{ $message }}</p>@enderror
    </div>
    <div>
      <label>{{ __('moderation.takedowns.claimant_email') }}
        <input type="email" name="claimant_email" value="{{ old('claimant_email') }}" required maxlength="255">
      </label>
      @error('claimant_email')<p>{{ $message }}</p>@enderror
    </div>
    <div>
      <label>{{ __('moderation.takedowns.reason') }}
        <textarea name="reason" required rows="5" maxlength="5000">{{ old('reason') }}</textarea>
      </label>
      @error('reason')<p>{{ $message }}</p>@enderror
    </div>
    <div>
      <label>{{ __('moderation.takedowns.proof') }}
        <input type="file" name="proof" accept=".pdf,.jpg,.jpeg,.png">
      </label>
      @error('proof')<p>{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="btn">{{ __('moderation.takedowns.file_claim') }}</button>
  </form>
@endsection
