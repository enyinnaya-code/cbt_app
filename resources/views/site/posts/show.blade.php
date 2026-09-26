@extends('layouts.public')

@section('title', $post->title)

@push('head')
    <meta name="description" content="{{ $post->summary(200) }}">
    <meta property="og:type" content="article">
    <meta property="og:title" content="{{ $post->title }}">
    <meta property="og:description" content="{{ $post->summary(200) }}">
    @if($post->coverUrl())<meta property="og:image" content="{{ $post->coverUrl() }}">@endif
    <meta name="twitter:card" content="summary_large_image">
@endpush

@section('content')
<article class="article">
    @if($preview)<div class="alert info">Preview: this article is not live yet, so only admins can see it.</div>@endif

    <div class="stack" style="gap:12px">
        <div class="row" style="gap:8px;flex-wrap:wrap">
            <a class="badge {{ $post->badgeClass() }}" href="{{ route(\App\Models\Post::categories()[$post->category]['path'] ?? 'news') }}" style="text-decoration:none">{{ $post->categoryLabel() }}</a>
            @if($post->exam)<span class="badge neutral">{{ $post->exam->name }}</span>@endif
        </div>
        <h1>{{ $post->title }}</h1>
        @if($post->excerpt)<p class="lead">{{ $post->excerpt }}</p>@endif
        <p class="small muted">{{ $post->published_at?->format('j F Y') ?? 'Draft' }} &middot; {{ $post->readMinutes() }} min read @if($post->author)&middot; {{ $post->author->name }}@endif</p>
    </div>

    @include('site._share')

    @if($post->coverUrl())<img class="hero-img" src="{{ $post->coverUrl() }}" alt="">@endif

    @if($post->category === 'scholarship' && ($post->deadline || $post->source || $post->link_url))
        <div class="facts">
            @if($post->source)<div><small>Offered by</small><b>{{ $post->source }}</b></div>@endif
            @if($post->deadline)<div><small>Deadline</small><b>{{ $post->deadline->format('j F Y') }} @if($post->isClosed())<span class="badge neutral">Closed</span>@endif</b></div>@endif
        </div>
    @endif

    <div class="prose">{!! $post->body !!}</div>

    @include('site._share')

    @if($post->link_url && ! $post->isClosed())
        <div><a class="btn btn-p" href="{{ $post->link_url }}" target="_blank" rel="noopener noreferrer"><x-icon name="link" size="s"/>{{ $post->category === 'scholarship' ? 'Apply or read more' : 'Read more' }}</a></div>
    @endif

    <div class="card row between wrap" style="background:var(--primary-soft);border:0">
        <div><p class="h3">Ready to practise?</p><p class="small muted">Past questions for WAEC, NECO, JAMB, Post-UTME and IGCSE, free to start.</p></div>
        <a class="btn btn-p" href="{{ auth()->check() ? route('practice.index') : route('register') }}">{{ auth()->check() ? 'Start practising' : 'Create a free account' }}</a>
    </div>
</article>

@if($related->isNotEmpty())
<section class="section">
    <div class="section-head"><h2 class="h2">More {{ strtolower($post->categoryLabel()) }}</h2></div>
    <div class="post-grid">@foreach($related as $post)@include('site.posts._card')@endforeach</div>
</section>
@endif
@endsection
