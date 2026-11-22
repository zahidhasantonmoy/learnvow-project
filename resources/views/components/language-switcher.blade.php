{{-- Language switcher: same page in the other locale. --}}
@php
$segments = request()->segments();
if (!empty($segments[0]) && in_array($segments[0], ['bn', 'en'], true)) {
    $rest = array_slice($segments, 1);
} else {
    $rest = $segments;
}
$currentLocale = app()->getLocale();
$bnUrl = url('/bn' . ($rest ? '/' . implode('/', $rest) : ''));
$enUrl = url('/en' . ($rest ? '/' . implode('/', $rest) : ''));
@endphp
<span class="lang-switch">
  <a href="{{ $bnUrl }}" class="{{ $currentLocale === 'bn' ? 'active' : '' }}">বাংলা</a>
  <span>·</span>
  <a href="{{ $enUrl }}" class="{{ $currentLocale === 'en' ? 'active' : '' }}">English</a>
</span>
