@extends('layouts.app')

@section('title', 'Mock result')
@section('page_class', 'mid')

@php
    $pct = $run->total ? $run->score / $run->total : 0;
    $circ = 2 * M_PI * 58;
    $secs = $result['duration_seconds'];
    $used = $secs < 60 ? $secs . 's' : ($secs >= 3600 ? intdiv($secs, 3600) . 'h ' : '') . intdiv($secs % 3600, 60) . 'm';
    $correctPct = $result['questions'] ? round($result['correct'] / $result['questions'] * 100) : 0;
@endphp

@section('content')
<div class="row between wrap">
    <div><p class="small muted">{{ $label }}</p><h1 class="h1">Your result</h1></div>
    <a class="btn btn-o" href="{{ route('mock.index') }}">Back to Mock</a>
</div>

<div class="col" style="align-items:center;gap:10px;padding:6px 0">
    <svg width="190" height="190" viewBox="0 0 140 140" role="img" aria-label="Score {{ $run->score }} out of {{ $run->total }}">
        <circle cx="70" cy="70" r="58" fill="none" stroke="var(--surface-2)" stroke-width="12"/>
        <circle cx="70" cy="70" r="58" fill="none" stroke="var(--primary)" stroke-width="12" stroke-linecap="round"
                stroke-dasharray="{{ round($circ * $pct, 1) }} {{ round($circ, 1) }}" transform="rotate(-90 70 70)"/>
        <text x="70" y="70" text-anchor="middle" font-family="Bricolage Grotesque, sans-serif" font-weight="800" font-size="34" fill="var(--text)">{{ $run->score }}</text>
        <text x="70" y="92" text-anchor="middle" font-family="Figtree, sans-serif" font-size="12" fill="var(--muted)">out of {{ $run->total }}</text>
    </svg>
    @if($change !== null)
        <span class="badge {{ $change >= 0 ? 'g' : 'r' }}"><x-icon name="trophy" size="s"/>{{ abs($change) }} {{ Str::plural('point', abs($change)) }} {{ $change >= 0 ? 'higher' : 'lower' }} than your last mock</span>
    @endif
</div>

<div class="grid3">
    <div class="stat"><span class="tiny muted">Time used</span><span class="h3">{{ $used }}</span></div>
    <div class="stat"><span class="tiny muted">Answered</span><span class="h3">{{ $result['answered'] }}/{{ $result['questions'] }}</span></div>
    <div class="stat"><span class="tiny muted">Correct</span><span class="h3">{{ $correctPct }}%</span></div>
</div>

<section class="card stack" style="gap:16px">
    <h2 class="h2">Score by subject</h2>
    @foreach($result['groups'] as $g)
        <div class="col" style="gap:6px">
            <div class="row between small"><span>{{ $g['name'] }}</span><b>{{ $g['correct'] }} of {{ $g['total'] }} &middot; {{ $g['percent'] }}</b></div>
            <div class="bar {{ $g['percent'] < $strong ? 'amber' : '' }}"><i style="width:{{ $g['percent'] }}%"></i></div>
        </div>
    @endforeach
    @if(count($result['groups']) > 1)<p class="hint">Each subject is marked out of 100, so the total is out of {{ $run->total }}.</p>@endif
</section>

@if($weak->isNotEmpty())
<section class="col" style="gap:10px">
    <h2 class="h3">Topics to work on</h2>
    <div class="chips">@foreach($weak as $t)<span class="chip">{{ $t['name'] }} <span class="muted">{{ $t['correct'] }}/{{ $t['total'] }}</span></span>@endforeach</div>
</section>
@endif

<div class="row wrap">
    <a class="btn btn-p" href="{{ route('mock.review', $run) }}">Review answers</a>
    <a class="btn btn-o" href="{{ route('dashboard') }}">Home</a>
</div>
@endsection
