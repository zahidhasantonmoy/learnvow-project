<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" data-theme="{{ request()->cookie('lv_theme', 'light') }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ ($seo['title'] ?? null) ? ($seo['title'] . ' | LearnVow') : 'LearnVow — ' . __('nav.tagline') }}</title>
<script>
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
    <div class="header-tools">
      <x-language-switcher />
      <x-theme-toggle />
    </div>
  </div>
</header>
<main class="container" style="max-width: 560px">
  @if(session('status'))
    <div class="alert">{{ session('status') }}</div>
  @endif
  @yield('content')
</main>
<footer class="site-footer">
  <div class="container">
    <p>LearnVow — {{ __('nav.tagline') }}</p>
  </div>
</footer>
@livewireScripts
@stack('scripts')
</body>
</html>
