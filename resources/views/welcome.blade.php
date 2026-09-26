@extends('layouts.public')

@section('title', 'Pass WAEC, NECO and JAMB')

@section('top_actions')
    <a class="btn btn-o btn-sm" href="{{ route('login') }}">Sign in</a>
@endsection

@section('content')
<section class="hero">
    <div class="stack" style="gap:24px">
        <div class="stack" style="gap:14px">
            <span class="badge g" style="align-self:flex-start"><x-icon name="check" size="s"/>Past questions, made simple</span>
            <h1>Pass WAEC, NECO and JAMB.</h1>
            <p class="muted" style="font-size:18px;max-width:52ch">Practise real past questions by subject and year, see the right answer straight away, and read a simple explanation in English or Pidgin.</p>
        </div>
        <div class="row wrap">
            <a class="btn btn-p" href="{{ route('register') }}">Get started, it is free</a>
            <a class="btn btn-o" href="{{ route('login') }}">I already have an account</a>
        </div>
        <p class="small muted">An offline mobile app for Android and iPhone is on the way.</p>
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
        <p class="muted">Pick WAEC, NECO or JAMB, choose a subject and a year, and answer at your own pace. Save hard questions to come back to.</p>
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
@endsection
