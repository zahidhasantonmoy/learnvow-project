<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ request()->cookie('lv_theme', 'light') }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ ($seo['title'] ?? null) ? ($seo['title'] . ' | LearnVow') : 'LearnVow — ' . __('nav.tagline') }}</title>
<script>
  // Apply the saved theme before first paint (no flash).
  try { var t = document.cookie.match(/(?:^|; )lv_theme=([^;]*)/); if (t && t[1]) document.documentElement.setAttribute('data-theme', t[1]); } catch (e) {}
</script>
@include('partials.seo', ['seo' => $seo ?? []])
@include('partials.styles')
@stack('styles')
@livewireStyles
</head>
<body>
<header class="site-header">
  <div class="container header-inner">
    <a href="{{ route('home', ['locale' => app()->getLocale()]) }}" class="brand serif">LearnVow<small>{{ __('nav.tagline') }}</small></a>
    {{-- UI-05: hamburger — the nav collapses below 768px (see styles). --}}
    <button type="button" class="nav-toggle" aria-label="{{ __('nav.menu') }}" aria-expanded="false" onclick="var h=this.closest('.site-header');h.classList.toggle('nav-open');this.setAttribute('aria-expanded',h.classList.contains('nav-open'));">☰</button>
    <nav class="main-nav">
      <a href="{{ route('home', ['locale' => app()->getLocale()]) }}">{{ __('nav.home') }}</a>
      <a href="{{ route('books.index', ['locale' => app()->getLocale()]) }}">{{ __('nav.browse') }}</a>
      <a href="{{ route('books.free', ['locale' => app()->getLocale()]) }}">{{ __('nav.free_books') }}</a>
      <a href="{{ route('search.index', ['locale' => app()->getLocale()]) }}">{{ __('nav.search') }}</a>
      @auth
        <a href="{{ route('library.index', ['locale' => app()->getLocale()]) }}">{{ __('nav.library') }}</a>
        <a href="{{ route('wishlist.index', ['locale' => app()->getLocale()]) }}">{{ __('nav.wishlist') }}</a>
      @endauth
    </nav>
    <div class="header-tools">
      @livewire('language-switcher')
      <x-theme-toggle />
      @auth
        <a href="{{ route('notifications.index', ['locale' => app()->getLocale()]) }}" aria-label="{{ __('nav.notifications') }}">{{ __('nav.notifications') }}@php $unreadCount = auth()->user()->unreadNotifications()->count(); @endphp @if($unreadCount > 0) ({{ $unreadCount }})@endif</a>
        <a href="{{ route('profile.show', ['locale' => app()->getLocale()]) }}">{{ __('nav.profile') }}</a>
        <form method="POST" action="{{ route('logout', ['locale' => app()->getLocale()]) }}" style="display:inline">
          @csrf
          <button type="submit" class="theme-btn">{{ __('nav.logout') }}</button>
        </form>
      @else
        <a href="{{ route('login', ['locale' => app()->getLocale()]) }}">{{ __('nav.login') }}</a>
        <a href="{{ route('register', ['locale' => app()->getLocale()]) }}" class="btn" style="padding:6px 14px">{{ __('nav.register') }}</a>
      @endauth
    </div>
  </div>
</header>
<main class="container">
  @if(session('status'))
    <div class="alert">{{ session('status') }}</div>
  @endif
  @yield('content')
</main>
<footer class="site-footer">
  <div class="container">
    <p>LearnVow — {{ __('nav.tagline') }}</p>
    <p>
      <a href="{{ route('pages.terms', ['locale' => app()->getLocale()]) }}">{{ __('nav.terms') }}</a> ·
      <a href="{{ route('pages.privacy', ['locale' => app()->getLocale()]) }}">{{ __('nav.privacy') }}</a> ·
      <a href="{{ route('pages.refund', ['locale' => app()->getLocale()]) }}">{{ __('nav.refund') }}</a> ·
      <a href="{{ route('pages.copyright', ['locale' => app()->getLocale()]) }}">{{ __('nav.copyright') }}</a> ·
      <a href="{{ route('takedown.create', ['locale' => app()->getLocale()]) }}">{{ __('nav.takedown') }}</a> ·
      <a href="{{ route('pages.help', ['locale' => app()->getLocale()]) }}">{{ __('nav.help') }}</a>
    </p>
  </div>
</footer>
@livewireScripts
@stack('scripts')
</body>
</html>
