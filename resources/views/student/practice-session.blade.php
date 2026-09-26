@extends('layouts.base')

@section('title', $title)

@section('body')
<header class="topbar">
    <div class="topbar-in">
        <a class="iconbtn" id="btn-exit" href="{{ $session['endpoints']['exit'] }}" aria-label="Leave practice"><x-icon name="x"/></a>
        <div class="grow center">
            <p class="h3" id="t-title">{{ $session['title'] }}</p>
            <p class="tiny muted" id="t-sub">{{ $session['subtitle'] }}</p>
        </div>
        <button class="iconbtn" id="btn-bookmark" type="button" aria-label="Save this question" aria-pressed="false"><x-icon name="bookmark"/></button>
    </div>
</header>

<main class="page mid" style="padding-bottom:130px">
    <noscript><div class="alert err">Practice needs JavaScript. Please turn it on in your browser.</div></noscript>

    <div class="runner" id="runner">
        <section id="stage" class="stack" style="gap:18px" aria-live="polite">
            <div class="col" style="gap:8px">
                <div class="row between small"><span id="q-count" style="font-weight:700"></span><span id="q-topic" class="muted"></span></div>
                <div class="bar"><i id="q-bar" style="width:0"></i></div>
            </div>
            <div id="q-passage" class="card flat prose" hidden></div>
            <div id="q-text" class="qtext prose"></div>
            <div id="q-options" class="stack" style="gap:10px" role="group" aria-label="Answer options"></div>
            <div id="q-feedback" role="status" hidden></div>
            <div id="q-explain" class="card flat stack" style="gap:12px" hidden></div>
        </section>

        <aside class="card stack" style="gap:14px" id="sheet-card">
            <div class="row between"><p class="h3">Answer sheet</p><span id="sheet-note" class="small muted"></span></div>
            <div id="sheet" class="sheet"></div>
            <div class="legend" id="legend"></div>
        </aside>
    </div>

    <section id="results" class="stack" style="gap:20px" hidden></section>
</main>

<div class="foot fixed" id="foot">
    <div class="inner">
        <button class="btn btn-o" id="btn-prev" type="button" style="flex:none" aria-label="Previous question"><x-icon name="chevL"/></button>
        <button class="btn btn-p" id="btn-next" type="button">Next question</button>
    </div>
</div>

<script type="application/json" id="session-data">{!! json_encode($session, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection

@push('scripts')
<script src="{{ asset('js/runner.js') }}"></script>
@endpush
