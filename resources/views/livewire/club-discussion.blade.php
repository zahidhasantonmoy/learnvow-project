<div>
  <h2>{{ __('community.discussion') }}</h2>

  @if($error)
    <p class="alert">{{ $error }}</p>
  @endif

  <form wire:submit.prevent="post">
    <p>
      <textarea wire:model="body" placeholder="{{ __('community.post_placeholder') }}" required></textarea>
    </p>
    <p><button type="submit" class="btn">{{ __('community.post') }}</button></p>
  </form>

  @foreach($posts as $post)
    <article>
      <p><strong>{{ $post->user->name ?? '' }}</strong> — {{ $post->created_at }}</p>
      <p>{{ $post->body }}</p>
      @can('delete', $post)
        <button wire:click="deletePost({{ $post->id }})">{{ __('community.delete_post') }}</button>
      @endcan
      {{-- Spec §6.2 (QA M4): report a club post — same flow as books/reviews. --}}
      <details>
        <summary>{{ __('community.report_post') }}</summary>
        <form method="POST" action="{{ route('reports.post', ['locale' => app()->getLocale(), 'club' => $club->id, 'post' => $post->id]) }}">
          @csrf
          <label>{{ __('moderation.reports.reason') }}
            <select name="reason" required>
              @foreach(['copyright', 'spam', 'abuse', 'wrong_content', 'other'] as $reason)
                <option value="{{ $reason }}">{{ __('moderation.reports.reason_'.$reason) }}</option>
              @endforeach
            </select>
          </label>
          <label>{{ __('moderation.reports.details') }}
            <textarea name="details" rows="3" maxlength="2000"></textarea>
          </label>
          <button type="submit" class="btn">{{ __('community.report_post') }}</button>
        </form>
      </details>
    </article>
  @endforeach
  {{ $posts->links() }}
</div>
