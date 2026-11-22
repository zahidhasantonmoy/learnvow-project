{{-- SEO partial. Controllers pass $seo = ['title','description','canonical','noindex','ogType','alternate'=>['bn'=>..,'en'=>..],'jsonLd'=>[...]]. --}}
@php $seo = $seo ?? []; @endphp
@if(!empty($seo['description']))
<meta name="description" content="{{ $seo['description'] }}">
@endif
@if(!empty($seo['noindex']))
<meta name="robots" content="noindex, nofollow">
@endif
<link rel="canonical" href="{{ $seo['canonical'] ?? url()->current() }}">
<link rel="alternate" hreflang="bn" href="{{ $seo['alternate']['bn'] ?? url('/bn') }}">
<link rel="alternate" hreflang="en" href="{{ $seo['alternate']['en'] ?? url('/en') }}">
<link rel="alternate" hreflang="x-default" href="{{ $seo['alternate']['en'] ?? url('/en') }}">
<meta property="og:title" content="{{ $seo['title'] ?? 'LearnVow' }}">
<meta property="og:description" content="{{ $seo['description'] ?? '' }}">
<meta property="og:type" content="{{ $seo['ogType'] ?? 'website' }}">
<meta property="og:url" content="{{ $seo['canonical'] ?? url()->current() }}">
@if(!empty($seo['jsonLd']))
<script type="application/ld+json">{!! json_encode($seo['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
