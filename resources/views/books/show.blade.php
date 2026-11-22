@extends('layouts.app')

@section('content')
  <div style="display:flex;gap:24px;flex-wrap:wrap">
    <div style="flex:0 0 220px;max-width:220px">
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
        <a class="btn" href="{{ route('books.sample', ['locale' => app()->getLocale(), 'slug' => $book->slug]) }}">{{ __('books.read_sample') }}</a>
        @auth
          @livewire('wishlist-button', ['book' => $book, 'inWishlist' => $inWishlist])
        @endauth
      </p>
    </div>
  </div>

  <h2 id="reviews">{{ __('books.reviews') }}</h2>
  @forelse($book->approvedReviews as $review)
    <div style="border-top:1px solid var(--line);padding:12px 0">
      <x-rating-stars :rating="$review->rating" :count="0" />
      @if($review->title)<strong>{{ $review->title }}</strong>@endif
      <p>{{ $review->body }}</p>
      <p class="book-author">{{ $review->user->name ?? '' }} · {{ $review->created_at->toDateString() }}</p>
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
