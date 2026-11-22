<div>
  @if($books->isEmpty())
    <p>{{ __('books.no_books') }}</p>
  @else
    <div class="book-grid">
      @foreach($books as $book)
        <x-book-card :book="$book" />
      @endforeach
    </div>
    <div style="margin-top:16px">{{ $books->links() }}</div>
  @endif
</div>
