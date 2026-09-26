@extends('layouts.public')

@php $siteInfo = app(\App\Support\SiteInfo::class); @endphp
@section('title', 'Pass ' . $siteInfo->examNames())
@section('meta_description', $siteInfo->description())

@push('head')
    {!! \App\Support\SiteInfo::jsonLd([
        '@context' => 'https://schema.org',
        '@graph' => [
            $siteInfo->organization() + ['@id' => url('/') . '#organization'],
            ['@type' => 'WebSite', 'name' => 'TestaCBT', 'url' => url('/'), 'inLanguage' => 'en-NG', 'publisher' => ['@id' => url('/') . '#organization']],
        ],
    ]) !!}
@endpush

@section('content')
<section class="hero">
    <div class="stack" style="gap:24px">
        <div class="stack" style="gap:14px">
            <span class="badge g" style="align-self:flex-start"><x-icon name="check" size="s"/>Past questions, made simple</span>
            <h1>Pass your exam. Start free.</h1>
            <p class="muted" style="font-size:18px;max-width:52ch">Practise real past questions for {{ $siteInfo->examNames() }}, see the right answer straight away, and read a simple explanation in English or Pidgin. Works offline on the app.</p>
        </div>
        <div class="row wrap">
            @auth
                <a class="btn btn-p" href="{{ route('dashboard') }}">Go to my dashboard</a>
                <a class="btn btn-o" href="{{ route('practice.index') }}">Start practising</a>
            @else
                <a class="btn btn-p" href="{{ route('register') }}">Get started, it is free</a>
                <a class="btn btn-o" href="{{ route('login') }}">I already have an account</a>
            @endauth
        </div>
        @if($exams->isNotEmpty())
            <div class="exam-strip">@foreach($exams as $exam)<a href="{{ route('register') }}">{{ $exam->name }}</a>@endforeach</div>
        @endif
    </div>

    <div class="hero-sheet" aria-hidden="true">
        <div class="row between"><span class="h3">JAMB practice</span><span class="badge g">Answer sheet</span></div>
        <div class="hero-row"><span class="q">1</span><span class="bub">A</span><span class="bub fill">B</span><span class="bub">C</span><span class="bub">D</span></div>
        <div class="hero-row"><span class="q">2</span><span class="bub">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub fill">D</span></div>
        <div class="hero-row"><span class="q">3</span><span class="bub fill">A</span><span class="bub">B</span><span class="bub">C</span><span class="bub">D</span></div>
        <div class="hero-row"><span class="q">4</span><span class="bub">A</span><span class="bub">B</span><span class="bub pencil">C</span><span class="bub">D</span></div>
    </div>
</section>

<section class="features">
    <div class="card stack" style="gap:10px">
        <span class="sq c-g"><x-icon name="book"/></span>
        <h2 class="h2">Practice by subject and year</h2>
        <p class="muted">Pick your exam, choose a subject and a year, and answer at your own pace. Save hard questions to come back to.</p>
    </div>
    <div class="card stack" style="gap:10px">
        <span class="sq c-a"><x-icon name="clock"/></span>
        <h2 class="h2">Mock exams that feel real</h2>
        <p class="muted">Timed like the real thing. The JAMB mock follows the CBT format: Use of English plus three subjects, 180 questions in 2 hours.</p>
    </div>
    <div class="card stack" style="gap:10px">
        <span class="sq c-p"><x-icon name="chart"/></span>
        <h2 class="h2">See what to work on</h2>
        <p class="muted">Track your score for each subject and your study streak, so you know where to spend your time before the exam.</p>
    </div>
</section>

<section class="section" id="news">
    <div class="section-head">
        <div><span class="eyebrow">Latest</span><h2 class="h1" style="font-size:clamp(24px,3.4vw,32px)">News and updates</h2><p>Exam news, result releases and announcements.</p></div>
        <a class="link" href="{{ route('news') }}">All news</a>
    </div>
    @if($news->isEmpty())
        <div class="card empty"><span class="sq c-b"><x-icon name="news"/></span><p class="h3">News is coming soon</p><p class="small muted">We will post exam news and result releases here.</p></div>
    @else
        <div class="post-grid">@foreach($news as $post)@include('site.posts._card')@endforeach</div>
    @endif
</section>

<section class="section split" id="events">
    <div>
        <div class="section-head" style="margin-bottom:6px">
            <div><span class="eyebrow">Coming up</span><h2 class="h1" style="font-size:clamp(24px,3.4vw,32px)">Upcoming events</h2></div>
            <a class="link" href="{{ route('events') }}">All events</a>
        </div>
        <div class="card">
            @forelse($events as $e)@include('site._event', ['e' => $e])
            @empty<div class="empty"><span class="sq c-g"><x-icon name="calendar"/></span><p class="h3">No events yet</p><p class="small muted">Exam dates and deadlines will appear here.</p></div>
            @endforelse
        </div>
    </div>
    <div>
        <div class="section-head" style="margin-bottom:6px">
            <div><span class="eyebrow">Apply</span><h2 class="h1" style="font-size:clamp(24px,3.4vw,32px)">Scholarships</h2></div>
            <a class="link" href="{{ route('scholarships') }}">See all</a>
        </div>
        <div class="card">
            @forelse($scholarships as $s)
                <a class="li" href="{{ route('articles.show', $s->slug) }}" style="text-decoration:none;color:inherit">
                    <span class="sq c-p"><x-icon name="cap"/></span>
                    <div class="grow"><p class="h3">{{ $s->title }}</p><p class="small muted">{{ $s->source ?: 'Scholarship' }}@if($s->deadline) &middot; closes {{ $s->deadline->format('j M Y') }}@endif</p></div>
                    <x-icon name="chevR" size="s"/>
                </a>
            @empty
                <div class="empty"><span class="sq c-p"><x-icon name="cap"/></span><p class="h3">No open scholarships right now</p><p class="small muted">We post new ones as they open.</p></div>
            @endforelse
        </div>
    </div>
</section>

<section class="section" id="pricing">
    <div class="card row between wrap" style="padding:28px;gap:20px">
        <div class="stack" style="gap:6px"><span class="eyebrow">Simple pricing</span><h2 class="h1" style="font-size:clamp(24px,3.4vw,32px)">Free to start. Pay only for what you need.</h2>
            <p class="muted" style="max-width:58ch">Try {{ $freeQuestions }} questions in every subject for free. Unlock a whole subject from {{ \App\Services\Pricing::naira($fromPrice) }}, by card or bank transfer. No subscription.</p></div>
        <a class="btn btn-p" href="{{ route('pricing') }}">See pricing</a>
    </div>
</section>

<section class="section" id="download">
    <div class="app-band">
        <div class="stack" style="gap:16px">
            <h2>Take TestaCBT with you</h2>
            @php $hasApp = $stores['android'] || $stores['apk'] || $stores['ios']; @endphp
            <p>{{ $hasApp ? 'Download the app once, then practise anywhere, even with no data. Your progress saves to your account when you are online.' : 'Add TestaCBT to your phone\'s home screen and open it like any other app. Your progress is saved to your account.' }}</p>
            @include('site._stores', ['stores' => $stores])
        </div>
        <ul>
            @if($hasApp)
            <li><x-icon name="wifioff"/>Works offline after the first download</li>
            @endif
            <li><x-icon name="clock"/>Timed mock exams that keep your place</li>
            @if($hasApp)
            <li><x-icon name="download"/>Small downloads, one subject at a time</li>
            @endif
            <li><x-icon name="user"/>Same account on the web and your phone</li>
        </ul>
    </div>
</section>
@endsection
