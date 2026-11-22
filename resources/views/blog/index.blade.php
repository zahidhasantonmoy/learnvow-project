@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.blog_title') }}</h1>
  <p>{{ __('books.blog_description') }}</p>
  @foreach($articles as $article)
    <article style="border-top:1px solid var(--line);padding:16px 0">
      <h2><a href="{{ route('blog.show', ['locale' => app()->getLocale(), 'slug' => $article['slug']]) }}">{{ app()->getLocale() === 'bn' ? $article['title'] : $article['title_en'] }}</a></h2>
      <p>{{ app()->getLocale() === 'bn' ? $article['excerpt'] : $article['excerpt_en'] }}</p>
      <p class="book-author">{{ $article['published_at'] }}</p>
    </article>
  @endforeach
@endsection
