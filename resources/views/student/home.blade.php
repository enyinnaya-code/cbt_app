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
<p class="small muted" style="margin-top:-4px">{{ $message }}</p>

@include('student._notices')

@foreach($endingSoon as $e)
    @php $left = \App\Services\Purchases::daysLeft($e); @endphp
    <div class="card row between wrap" style="background:var(--accent-soft);border:0">
        <div><p class="h3">{{ $e->subject->name }} ends {{ $left <= 0 ? 'today' : 'in ' . $left . ' ' . \Illuminate\Support\Str::plural('day', $left) }}</p>
            <p class="small">Renew now to keep every question. Your progress stays either way.</p></div>
        <a class="btn btn-p btn-sm" href="{{ \App\Services\Purchases::renewUrl($e) }}">Renew</a>
    </div>
@endforeach

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


<div class="grid2">
    {{-- XP and level --}}
    <div class="card stack" style="gap:12px">
        <div class="row between">
            <div class="row" style="gap:12px">
                <div class="sq c-a"><x-icon name="trophy"/></div>
                <div><p class="h3">Level {{ $level['level'] }}: {{ $level['title'] }}</p><p class="small muted">{{ number_format($level['xp']) }} XP{{ $xpToday ? ' · +' . $xpToday . ' today' : '' }}</p></div>
            </div>
        </div>
        <div class="bar" role="progressbar" aria-valuenow="{{ $level['pct'] }}" aria-valuemin="0" aria-valuemax="100"><i style="width:{{ $level['pct'] }}%"></i></div>
        <p class="small muted">{{ $level['to_next'] !== null ? number_format($level['to_next']) . ' XP to ' . $level['next_title'] : 'You have reached the top level.' }}</p>
    </div>

    {{-- Daily challenge --}}
    <div class="card stack" style="gap:12px">
        @if($challenge)
            <div class="row between"><p class="h3">Today's challenge</p><span class="badge {{ $challenge->done ? 'g' : '' }}">{{ $challenge->done ? 'Done' : '+' . $challenge->xp . ' XP' }}</span></div>
            <p class="small muted">{{ $challenge->subject }} · {{ $challenge->total }} questions</p>
            @if($challenge->done)
                <p class="small">You scored {{ $challenge->correct }} of {{ $challenge->total }}. A new challenge is ready tomorrow.</p>
                <div class="row wrap" style="gap:8px"><a class="btn btn-o btn-sm" href="{{ route('challenge.show') }}">Review it</a><a class="btn btn-o btn-sm" href="{{ route('rankings') }}">See rankings</a></div>
            @else
                <div class="bar"><i style="width:{{ $challenge->total ? (int) round($challenge->answered / $challenge->total * 100) : 0 }}%"></i></div>
                <div class="row wrap" style="gap:8px"><a class="btn btn-p btn-sm" href="{{ route('challenge.show') }}">{{ $challenge->answered ? 'Continue (' . $challenge->answered . '/' . $challenge->total . ')' : 'Start challenge' }}</a><a class="btn btn-o btn-sm" href="{{ route('rankings') }}">Rankings</a></div>
            @endif
        @elseif($joined)
            <p class="h3">Today's challenge</p>
            <p class="small muted">It appears here as soon as there are questions to practise.</p>
        @else
            <p class="h3">Daily challenge</p>
            <p class="small muted">10 questions a day, ranked against other students.</p>
            <a class="btn btn-p btn-sm" style="align-self:flex-start" href="{{ route('rankings.join') }}">Join the daily challenge</a>
        @endif
    </div>
</div>

@if($weakTopic || $weakSubject)
<section class="card stack" style="gap:10px">
    <p class="h3">Recommended for you</p>
    @if($weakTopic)
        <p>You need more practice in <strong>{{ $weakTopic->topic }}</strong> ({{ $weakTopic->subject }}). Your accuracy is {{ $weakTopic->accuracy }}%.</p>
        <a class="btn btn-p btn-sm" style="align-self:flex-start" href="{{ route('practice.index', ['subject' => $weakTopic->subject_slug]) }}">Practise {{ $weakTopic->subject }}</a>
    @else
        <p>Your accuracy in <strong>{{ $weakSubject->name }}</strong> is {{ $weakSubject->accuracy }}%. A short session will lift it.</p>
        <a class="btn btn-p btn-sm" style="align-self:flex-start" href="{{ route('practice.index', ['subject' => \Illuminate\Support\Str::slug($weakSubject->name)]) }}">Practise {{ $weakSubject->name }}</a>
    @endif
</section>
@endif

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
<section class="card">
    <div class="row between" style="margin-bottom:8px"><h2 class="h2">Badges</h2><span class="small muted">{{ $badges->where('earned')->count() }} of {{ $badges->count() }}</span></div>
    <div class="grid4">
        @foreach($badges as $b)
            <div class="stack" style="gap:6px;align-items:center;text-align:center;{{ $b['earned'] ? '' : 'opacity:.45' }}" title="{{ $b['text'] }}">
                <span class="sq {{ $b['earned'] ? 'c-a' : '' }}"><x-icon name="{{ $b['icon'] }}"/></span>
                <span class="small" style="font-weight:700">{{ $b['title'] }}</span>
                <span class="tiny muted">{{ $b['text'] }}</span>
            </div>
        @endforeach
    </div>
</section>
@endsection
