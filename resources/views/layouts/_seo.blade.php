{{-- Everything search engines and link previews read. Pages fill it in with @section: title, meta_description, og_image, og_type, robots, canonical. --}}
@php
    $site = app(\App\Support\SiteInfo::class);
    $pageTitle = trim($__env->yieldContent('title')) ?: 'TestaCBT';
    $fullTitle = $pageTitle === 'TestaCBT' ? 'TestaCBT' : $pageTitle . ' | TestaCBT';
    $description = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($__env->yieldContent('meta_description') ?: $site->description()))), 158);
    $canonical = trim($__env->yieldContent('canonical')) ?: $site->canonical(request());
    $image = trim($__env->yieldContent('og_image')) ?: asset('og-default.png');
    $robots = trim($__env->yieldContent('robots')) ?: 'index, follow, max-image-preview:large, max-snippet:-1';
    $ogType = trim($__env->yieldContent('og_type')) ?: 'website';
@endphp
    <title>{{ $fullTitle }}</title>
    <meta name="description" content="{{ $description }}">
    <meta name="robots" content="{{ $robots }}">
    <link rel="canonical" href="{{ $canonical }}">
@foreach($site->verification() as $name => $token)
    <meta name="{{ $name }}" content="{{ $token }}">
@endforeach

    <meta property="og:site_name" content="TestaCBT">
    <meta property="og:locale" content="en_NG">
    <meta property="og:type" content="{{ $ogType }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $image }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $pageTitle }}">
    <meta name="twitter:description" content="{{ $description }}">
    <meta name="twitter:image" content="{{ $image }}">

    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('site.webmanifest') }}">
    <meta name="theme-color" content="#0A7A4A">
    <meta name="application-name" content="TestaCBT">
    <meta name="apple-mobile-web-app-title" content="TestaCBT">
    <link rel="alternate" type="application/rss+xml" title="TestaCBT news" href="{{ url('/feed.xml') }}">
