@extends('layouts.app')

@section('content')
  <h1 class="serif">{{ __('books.help_title') }}</h1>
  <p>{{ __('books.help_intro') }}</p>
  <h2>{{ __('books.help_reading_heading') }}</h2>
  <p>{{ __('books.help_reading_body') }}</p>
  <h2>{{ __('books.help_payment_heading') }}</h2>
  <p>{{ __('books.help_payment_body') }}</p>
  <h2>{{ __('books.help_contact_heading') }}</h2>
  <p>{{ __('books.help_contact_body') }}</p>
  @if ($faqsByCategory->isNotEmpty())
    <h2>{{ __('books.help_faq_heading') }}</h2>
    @foreach ($faqsByCategory as $category => $faqs)
      <h3>{{ $category ?: __('books.help_faq_general') }}</h3>
      @foreach ($faqs as $faq)
        <h4>{{ app()->getLocale() === 'en' ? ($faq->question_en ?: $faq->question) : $faq->question }}</h4>
        <p>{{ app()->getLocale() === 'en' ? ($faq->answer_en ?: $faq->answer) : $faq->answer }}</p>
      @endforeach
    @endforeach
  @endif
@endsection
