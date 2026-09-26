@extends('layouts.app')

@section('title', 'Home')

@php $tones = ['c-b', 'c-a', 'c-g', 'c-p']; @endphp

@section('content')
<div class="row between wrap">
    <div>
        <p class="small muted">{{ $greeting }}</p>
        <h1 class="h1">{{ $firstName }}</h1>
    </div>
</div>

<div class="grid2">
    {{-- Countdown and streak --}}
    <div class="card primary">
        @if($daysToExam !== null)
            <p class="small" style="opacity:.85">{{ $targetExam?->name ?? 'Your exam' }}</p>
            <div class="row between" style="align-items:flex-end;margin-top:4px">
                <p class="display" style="font-size:44px;font-weight:800;line-height:1">{{ $daysToExam }} <span style="font-size:18px;font-weight:600">{{ Str::plural('day', $daysToExam) }} left</span></p>
                <span class="row small" style="gap:4px;font-weight:700"><x-icon name="flame" size="s"/>{{ $streak }}-day streak</span>
            </div>
        @else
            <div class="row between" style="align-items:flex-end">
                <div>
                    <p class="small" style="opacity:.85">Study streak</p>
                    <p class="display" style="font-size:44px;font-weight:800;line-height:1">{{ $streak }} <span style="font-size:18px;font-weight:600">{{ Str::plural('day', $streak) }}</span></p>
                </div>
                @if(Route::has('profile'))<a class="small" style="color:inherit;font-weight:700" href="{{ route('profile') }}">Set your exam date</a>@endif
            </div>
        @endif
    </div>

    {{-- Carry on --}}
    <div class="card stack" style="gap:14px">
        @if($last)
            <div class="row between"><p class="h3">Pick up where you stopped</p><span class="badge">{{ $last->exam }} {{ $last->year }}</span></div>
            <div class="row">
                <div class="sq c-p">{{ $last->code }}</div>
                <div class="grow"><p class="h3">{{ $last->subject }}</p><p class="small muted">Last practised {{ \Illuminate\Support\Carbon::parse($last->answered_at)->diffForHumans() }}</p></div>
                @if(Route::has('practice.index'))
                    <a class="iconbtn" style="background:var(--primary);color:var(--on-primary);border:0;border-radius:50%" aria-label="Continue {{ $last->subject }}"
                       href="{{ route('practice.index', ['exam' => $last->exam_slug, 'subject' => $last->subject_slug]) }}"><x-icon name="play" size="s"/></a>
                @endif
            </div>
        @else
            <p class="h3">Start your first practice</p>
            <p class="small muted">Choose an exam and a subject, answer a few questions, and your progress will show up here.</p>
            @if(Route::has('practice.index'))<a class="btn btn-p btn-sm" style="align-self:flex-start" href="{{ route('practice.index') }}">Start practising</a>@endif
        @endif
    </div>
</div>

<div class="grid4">
    @if(Route::has('practice.index'))
    <a class="qa" href="{{ route('practice.index') }}"><span class="sq c-g"><x-icon name="book"/></span><span><span class="h3" style="display:block">Practice</span><span class="small muted">By subject and year</span></span></a>
    @endif
    @if(Route::has('mock.index'))
    <a class="qa" href="{{ route('mock.index') }}"><span class="sq c-a"><x-icon name="clock"/></span><span><span class="h3" style="display:block">Mock exam</span><span class="small muted">Timed, like CBT</span></span></a>
    @endif
    @if(Route::has('saved'))
    <a class="qa" href="{{ route('saved') }}"><span class="sq c-b"><x-icon name="bookmark"/></span><span><span class="h3" style="display:block">Saved</span><span class="small muted">Questions to revisit</span></span></a>
    @endif
    @if(Route::has('progress'))
    <a class="qa" href="{{ route('progress') }}"><span class="sq c-p"><x-icon name="chart"/></span><span><span class="h3" style="display:block">Progress</span><span class="small muted">{{ number_format($answered) }} answered</span></span></a>
    @endif
</div>

<section class="card">
    <div class="row between" style="margin-bottom:4px">
        <h2 class="h2">Your subjects</h2>
        @if(Route::has('progress') && $subjects->isNotEmpty())<a class="link small" href="{{ route('progress') }}">See all</a>@endif
    </div>
    @forelse($subjects->take(5) as $i => $s)
        <div class="li">
            <div class="sq {{ $tones[$i % 4] }}">{{ $s->code }}</div>
            <div class="grow col" style="gap:6px">
                <div class="row between"><span class="h3">{{ $s->name }}</span><span class="small muted">{{ $s->accuracy }}% of {{ $s->answered }}</span></div>
                <div class="bar {{ $s->accuracy < config('testacbt.strong_accuracy') ? 'amber' : '' }}"><i style="width:{{ $s->accuracy }}%"></i></div>
            </div>
        </div>
    @empty
        <div class="empty">
            <span class="sq c-g"><x-icon name="chart"/></span>
            <p class="h3">Nothing here yet</p>
            <p class="small muted">Your score in each subject will appear once you have answered some questions.</p>
        </div>
    @endforelse
</section>
@endsection
