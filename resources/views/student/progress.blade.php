@extends('layouts.app')

@section('title', 'Progress')
@section('page_class', 'mid')

@php $tones = ['c-b', 'c-a', 'c-g', 'c-p']; $strong = config('testacbt.strong_accuracy'); @endphp

@section('content')
<div class="col"><h1 class="h1">Your progress</h1></div>

@if($total === 0)
    <div class="card empty">
        <span class="sq c-g"><x-icon name="chart"/></span>
        <p class="h3">No progress yet</p>
        <p class="small muted">Answer some questions in Practice and your scores will show up here.</p>
        <a class="btn btn-p btn-sm" href="{{ route('practice.index') }}">Start practising</a>
    </div>
@else
    <div class="grid3">
        <div class="stat"><span class="small muted">Questions answered</span><span class="big">{{ number_format($total) }}</span></div>
        <div class="stat"><span class="small muted">Correct</span><span class="big">{{ $accuracy }}%</span></div>
        <div class="stat"><span class="small muted">Study streak</span><span class="big row" style="gap:6px"><x-icon name="flame" size="l"/>{{ $streak }} {{ Str::plural('day', $streak) }}</span></div>
    </div>

    <section class="card stack" style="gap:16px">
        <div class="row between"><div><p class="small muted">Last 7 days</p><p class="display" style="font-size:28px;font-weight:800">{{ number_format($thisWeek) }} <span class="small muted" style="font-weight:500">answered</span></p></div></div>
        <div class="chart" role="img" aria-label="Questions answered each day for the last 7 days">
            @foreach($days as $d)
                <div><span class="b {{ $d['today'] ? 'on' : '' }}" style="height:{{ max(3, round($d['count'] / $maxDay * 100)) }}%" title="{{ $d['count'] }}"></span>{{ $d['label'] }}</div>
            @endforeach
        </div>
    </section>

    @if($subjects->count() > 1)
    <div class="grid2">
        <div class="stat"><span class="tiny muted">Strongest</span><span class="h3">{{ $strongest->name }}</span><span class="big" style="color:var(--primary)">{{ $strongest->accuracy }}%</span></div>
        <div class="stat"><span class="tiny muted">Needs work</span><span class="h3">{{ $weakest->name }}</span><span class="big" style="color:var(--accent-ink)">{{ $weakest->accuracy }}%</span></div>
    </div>
    @endif

    <section class="card">
        <h2 class="h2" style="margin-bottom:4px">Score by subject</h2>
        @foreach($subjects->sortByDesc('answered')->values() as $i => $s)
            <div class="li">
                <div class="sq {{ $tones[$i % 4] }}">{{ $s->code }}</div>
                <div class="grow col" style="gap:6px">
                    <div class="row between"><span class="h3">{{ $s->name }}</span><span class="small muted">{{ $s->correct }} of {{ $s->answered }} &middot; {{ $s->accuracy }}%</span></div>
                    <div class="bar {{ $s->accuracy < $strong ? 'amber' : '' }}"><i style="width:{{ $s->accuracy }}%"></i></div>
                </div>
            </div>
        @endforeach
    </section>

    @if($weakTopics->isNotEmpty())
    <section class="card">
        <h2 class="h2" style="margin-bottom:4px">Topics to work on</h2>
        @foreach($weakTopics as $t)
            <div class="li">
                <div class="grow"><p class="h3">{{ $t->topic }}</p><p class="small muted">{{ $t->subject }}, {{ $t->correct }} of {{ $t->answered }} correct</p></div>
                <a class="btn btn-o btn-sm" href="{{ route('practice.session', ['subject' => $t->subject_slug, 'topic' => $t->topic_id, 'mode' => 'instant', 'count' => 20]) }}">Practise</a>
            </div>
        @endforeach
    </section>
    @endif

    @if($mocks->isNotEmpty())
    <section class="card">
        <h2 class="h2" style="margin-bottom:4px">Recent mock exams</h2>
        @foreach($mocks as $m)
            <div class="li">
                <div class="grow"><p class="h3">{{ $m->exam }} mock</p><p class="small muted">{{ \Illuminate\Support\Carbon::parse($m->taken_at)->format('j F Y g:i a') }}</p></div>
                <span class="h3">{{ $m->score }} / {{ $m->total }}</span>
            </div>
        @endforeach
    </section>
    @endif
@endif
@endsection
