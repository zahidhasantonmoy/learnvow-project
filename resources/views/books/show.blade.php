@extends('layouts.app')

@section('content')
  <div style="display:flex;gap:24px;flex-wrap:wrap">
    <div class="book-detail-cover" style="flex:0 0 220px;max-width:220px">
      <x-cover :book="$book" />
    </div>
    <div style="flex:1;min-width:260px">
      <h1 class="serif">{{ $book->title }}</h1>
      <p><a href="{{ route('authors.show', ['locale' => app()->getLocale(), 'slug' => $book->author->slug]) }}">{{ $book->author->name }}</a></p>
      <p><a href="{{ route('categories.show', ['locale' => app()->getLocale(), 'slug' => $book->category->slug]) }}">{{ $book->category->displayName() }}</a></p>
      <x-rating-stars :rating="$book->rating_avg" :count="$book->ratings_count" />
      <p>
        @if($book->list_price_bdt)
          <del>৳{{ number_format($book->list_price_bdt) }}</del>
        @endif
        <strong>{{ $book->price_bdt > 0 ? '৳'.number_format($book->price_bdt) : __('books.free') }}</strong>
      </p>
      <p>{{ __('books.copies_sold', ['count' => number_format($book->sales_count)]) }}</p>
      <p>{{ __('books.pages', ['count' => $book->pages]) }} · {{ $book->language === 'bn' ? __('books.bangla') : __('books.english') }}
        @if($book->has_audiobook) · {{ __('books.has_audiobook') }} @endif
        @if($book->is_public_domain) · {{ __('books.public_domain') }} @endif
      </p>
      <p>{{ $book->language === 'bn' ? $book->description : ($book->description_en ?? $book->description) }}</p>
      @if($book->tags->isNotEmpty())
        <p>
          @foreach($book->tags as $tag)
            <span class="badge">{{ app()->getLocale() === 'bn' ? $tag->name : ($tag->name_en ?? $tag->name) }}</span>
          @endforeach
        </p>
      @endif
      <p>
        {{-- UI-01/UI-02: the core Buy / Read CTAs. Read when the user can
             read the book (owned / Premium / free); Buy when it is a
             purchasable published book they do not own yet. The author's
             own book never shows Buy (checkout refuses it anyway). --}}
        @if($canRead)
          <a class="btn" href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}">{{ __('books.read_now') }}</a>
        @elseif($book->is_free && $book->status === 'published')
          <a class="btn" href="{{ route('reader.show', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}">{{ __('books.read_now') }}</a>
        @elseif($book->status === 'published' && ! $book->is_paused && ! (auth()->check() && $book->author && (int) $book->author->user_id === (int) auth()->id()))
          <a class="btn" href="{{ route('checkout.book', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}">{{ __('books.buy_now', ['price' => number_format($book->price_bdt)]) }}</a>
        @endif
        <a class="btn" href="{{ route('books.sample', ['locale' => app()->getLocale(), 'slug' => $book->slug]) }}">{{ __('books.read_sample') }}</a>
        @auth
          @livewire('wishlist-button', ['book' => $book, 'inWishlist' => $inWishlist])
          {{-- Phase 4 §4.2 — add to a shelf (collections). --}}
          @php $myCollections = \App\Models\Collection::where('user_id', auth()->id())->orderBy('name')->get(); @endphp
          @if($myCollections->isNotEmpty())
            <form method="POST" action="{{ route('collections.books.add', ['locale' => app()->getLocale(), 'collection' => $myCollections->first()->id, 'book' => $book->slug]) }}" style="display:inline" id="add-to-collection-form">
              @csrf
              <label>{{ __('books.collections_add_to') }}
                <select name="collection_id" onchange="this.form.action = this.form.action.replace(/collections\/\d+/, 'collections/' + this.value)">
                  @foreach($myCollections as $collection)
                    <option value="{{ $collection->id }}">{{ $collection->name }}</option>
                  @endforeach
                </select>
              </label>
              <button type="submit" class="btn">{{ __('books.collections_add_to') }}</button>
            </form>
          @else
            <a class="btn" href="{{ route('collections.index', ['locale' => app()->getLocale()]) }}">{{ __('books.collections_none_yet') }}</a>
          @endif
        @endauth
      </p>
      {{-- Phase 4 §2.1 / §4.8 (QA M3): print order + pre-order were
           undiscoverable — no view linked them. Print link only when
           the reader can actually order (mirrors PrintOrderController);
           the price itself stays unset by design (Super Admin sets it). --}}
      @if($book->allow_pod && $book->status === 'published' && ! $book->is_paused)
        <p><a class="btn" href="{{ route('print-orders.create', ['locale' => app()->getLocale(), 'book' => $book->slug]) }}">{{ __('print.order_title') }}</a></p>
      @endif
      @auth
        @if(app(\App\Http\Controllers\PreorderController::class)->isPreorderable($book))
          @livewire('preorder-button', ['book' => $book])
        @endif
      @endauth
      @auth
        <details>
          <summary>{{ __('moderation.reports.report_book') }}</summary>
          <form method="POST" action="{{ route('reports.book', ['locale' => app()->getLocale(), 'slug' => $book->slug]) }}">
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
            <button type="submit" class="btn">{{ __('moderation.reports.report_book') }}</button>
          </form>
        </details>
      @endauth
    </div>
  </div>

  <h2 id="reviews">{{ __('books.reviews') }}</h2>
  @forelse($book->approvedReviews as $review)
    <div style="border-top:1px solid var(--line);padding:12px 0">
      <x-rating-stars :rating="$review->rating" :count="0" />
      @if($review->title)<strong>{{ $review->title }}</strong>@endif
      <p>{{ $review->body }}</p>
      <p class="book-author">{{ $review->user->name ?? '' }} · {{ $review->created_at->toDateString() }}</p>
      @auth
        <details>
          <summary>{{ __('moderation.reports.report_review') }}</summary>
          <form method="POST" action="{{ route('reports.review', ['locale' => app()->getLocale(), 'review' => $review->id]) }}">
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
            <button type="submit" class="btn">{{ __('moderation.reports.report_review') }}</button>
          </form>
        </details>
      @endauth
    </div>
  @empty
    <p>{{ __('books.no_reviews_yet') }}</p>
  @endforelse

  @auth
    <h3>{{ __('books.write_review') }}</h3>
    @if($myReview && $myReview->status === 'pending')
      <div class="alert">{{ __('books.review_pending_notice') }}</div>
    @endif
    @livewire('review-form', ['book' => $book])
  @else
    <p>{{ __('books.login_to_review') }}</p>
  @endauth

  @if($related->isNotEmpty())
    <h2>{{ __('books.related_books') }}</h2>
    <div class="book-grid">
      @foreach($related as $relatedBook)
        <x-book-card :book="$relatedBook" />
      @endforeach
    </div>
  @endif
@endsection
