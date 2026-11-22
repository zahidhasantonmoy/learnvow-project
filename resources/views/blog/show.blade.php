@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ app()->getLocale() === 'bn' ? $article['title'] : $article['title_en'] }}</h1>
  <p class="book-author">{{ $article['published_at'] }}</p>
  <div>{!! nl2br(e(app()->getLocale() === 'bn' ? $article['body'] : $article['body_en'])) !!}</div>
  <p><a href="{{ route('blog.index', ['locale' => app()->getLocale()]) }}">&larr; {{ __('books.blog_title') }}</a></p>
@endsection
