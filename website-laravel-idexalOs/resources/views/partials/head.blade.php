@php
    $title = trim($__env->yieldContent('title')) ?: __('ui.meta.title');
    $description = trim($__env->yieldContent('description')) ?: __('ui.meta.description');
    $robots = trim($__env->yieldContent('robots')) ?: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1';

    $defaultLocale = default_locale();
    $defaultLocaleTag = str_replace('_', '-', $defaultLocale);
    $currentLocale = app()->getLocale();

    // The default language lives at the bare URL; every other language is an
    // addressable, shareable and indexable variant of the same page.
    $localized = static function (string $locale) use ($defaultLocale): string {
        return locale_url($locale === $defaultLocale ? null : $locale);
    };

    $canonical = $localized($currentLocale);

    // One share card per language: the preview a reader sees is written in the
    // language they will land on when they open it.
    $ogImage = asset($currentLocale === 'ar' ? 'brand/og-ar.png' : 'brand/og-default.png');
    $ogImageAlt = config('idexal.brand.name').' — '.__('ui.landing.hero_title');
    $ogLocale = $currentLocale === 'ar' ? 'ar_AR' : 'en_US';
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

{{-- The typefaces are the first thing the page paints, so the connection is
     opened while the HTML is parsed instead of after the stylesheet loads. --}}
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>
<link rel="stylesheet" href="https://fonts.bunny.net/css?family=ibm-plex-mono:400,500,600|ibm-plex-sans-arabic:300,400,500,600,700&display=swap">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#080a08">

<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<meta name="robots" content="{{ $robots }}">

<link rel="canonical" href="{{ $canonical }}">

@foreach (supported_locales() as $locale)
    <link rel="alternate" hreflang="{{ str_replace('_', '-', $locale) }}" href="{{ $localized($locale) }}">
@endforeach
<link rel="alternate" hreflang="x-default" href="{{ $localized($defaultLocale) }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('idexal.brand.name') }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:locale" content="{{ $ogLocale }}">
@foreach (supported_locales() as $locale)
    @continue($locale === $currentLocale)
    <meta property="og:locale:alternate" content="{{ $locale === 'ar' ? 'ar_AR' : 'en_US' }}">
@endforeach
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="{{ $ogImageAlt }}">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $ogImage }}">

<link rel="icon" type="image/png" href="{{ asset('brand/icon-light.png') }}">
<link rel="apple-touch-icon" href="{{ asset('brand/icon-light.png') }}">

@include('partials.schema')

@vite(['resources/css/app.css', 'resources/js/app.js'])
@stack('head')
