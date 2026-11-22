{{-- Gradient cover (prototype has no <img> covers yet — A6). Contract: $book with cover_c1/cover_c2/title/author. --}}
@props(['book'])
<div class="book-cover" style="background: linear-gradient(135deg, {{ $book->cover_c1 ?? '#17573a' }}, {{ $book->cover_c2 ?? '#0d3a26' }});">
  <span class="book-cover-title serif">{{ $book->title }}</span>
  <span class="book-cover-author">{{ $book->author->name ?? '' }}</span>
</div>
