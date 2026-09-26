@extends('layouts.base')

@section('title', $title)

@section('body')
<header class="topbar">
    <div class="topbar-in">
        <a class="iconbtn" href="{{ $session['endpoints']['exit'] }}" aria-label="Leave for now (the clock keeps running)"><x-icon name="chevL"/></a>
        <div class="grow">
            <p class="h3">{{ $session['title'] }}</p>
            <p class="tiny muted">{{ $session['subtitle'] }}</p>
        </div>
        <div class="timer" id="timer" role="timer" aria-live="off"><x-icon name="clock" size="s"/><span id="timer-text">--:--</span></div>
    </div>
</header>

<main class="page mid" style="padding-bottom:130px">
    <noscript><div class="alert err">The mock exam needs JavaScript. Please turn it on in your browser.</div></noscript>

    <div class="chips scroll" id="tabs" role="tablist" aria-label="Subjects"></div>

    <div class="runner" id="runner">
        <section id="stage" class="stack" style="gap:18px">
            <div class="row between">
                <p class="h3" id="q-label"></p>
                <button class="btn btn-o btn-sm" id="btn-flag" type="button" aria-pressed="false"><x-icon name="flag" size="s"/>Flag</button>
            </div>
            <div id="q-passage" class="card flat prose" hidden></div>
            <div id="q-text" class="qtext prose"></div>
            <div id="q-options" class="stack" style="gap:10px" role="group" aria-label="Answer options"></div>
        </section>

        <aside class="card stack" style="gap:14px">
            <div class="row between"><p class="h3">Answer sheet</p><span id="sheet-note" class="small muted"></span></div>
            <div id="sheet" class="sheet"></div>
            <div class="legend">
                <span><b style="background:var(--text);border-color:var(--text)"></b>Answered</span>
                <span><b style="background:var(--accent-soft);border-color:var(--accent)"></b>Flagged</span>
                <span><b></b>Not answered</span>
            </div>
            <button class="btn btn-o block" id="btn-submit-side" type="button">Submit exam</button>
        </aside>
    </div>
</main>

<div class="foot fixed">
    <div class="inner">
        <button class="btn btn-o" id="btn-prev" type="button">Previous</button>
        <button class="btn btn-p" id="btn-next" type="button">Next</button>
    </div>
</div>

<div id="saving" class="tiny muted" style="position:fixed;left:16px;bottom:74px;z-index:11" aria-live="polite"></div>

<script type="application/json" id="session-data">{!! json_encode($session, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endsection

@push('scripts')
<script src="{{ asset('js/mock.js') }}"></script>
@endpush
