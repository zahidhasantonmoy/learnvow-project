{{-- Book card. Contract: $book (Book model with author relation, cover_c1/cover_c2, rating_avg, ratings_count, price_bdt, slug). --}}
@props(['book'])
<a href="{{ route('books.show', ['locale' => app()->getLocale(), 'slug' => $book->slug]) }}" class="book-card">
  <x-cover :book="$book" />
  <div class="book-card-body">
    <h3 class="serif">{{ $book->title }}</h3>
    <p class="book-author">{{ $book->author->name ?? '' }}</p>
    <x-rating-stars :rating="$book->rating_avg" :count="$book->ratings_count" />
    <p class="book-price">{{ $book->price_bdt > 0 ? '৳' . number_format($book->price_bdt) : __('books.free') }}</p>
  </div>
</a>
