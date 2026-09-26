@extends('layouts.app')

@section('title', 'Overview')

@section('content')
<div class="row between wrap">
    <div class="col"><h1 class="h1">Overview</h1><p class="muted">What students can practise, and what is waiting for you.</p></div>
    <a class="btn btn-p" href="{{ route('console.papers.create') }}"><x-icon name="plus" size="s"/>New paper</a>
</div>

@if($legacy > 0)
    <a class="card row between" href="{{ route('console.legacy.index') }}" style="border-color:var(--accent)">
        <div class="row grow"><span class="sq c-a"><x-icon name="refresh"/></span>
            <div><p class="h3">{{ $legacy }} old {{ Str::plural('test', $legacy) }} still to organise</p>
                <p class="small muted">Give each one an exam, subject and year so its questions can be used for practice.</p></div></div>
        <span class="link">Open</span>
    </a>
@endif

<div class="grid4">
    <div class="stat"><span class="small muted">Live questions</span><span class="big">{{ number_format($liveQuestions) }}</span><span class="tiny muted">{{ number_format($questions) }} in total</span></div>
    <div class="stat"><span class="small muted">Published papers</span><span class="big">{{ $papers['published'] ?? 0 }}</span><span class="tiny muted">{{ $packs }} offline {{ Str::plural('pack', $packs) }}</span></div>
    <div class="stat"><span class="small muted">Draft papers</span><span class="big">{{ $papers['draft'] ?? 0 }}</span><span class="tiny muted">not visible to students</span></div>
    <div class="stat"><span class="small muted">Students</span><span class="big">{{ number_format($students) }}</span><span class="tiny muted">{{ $newStudents }} new this week</span></div>
</div>

<section class="card">
    <div class="row between" style="margin-bottom:4px"><h2 class="h2">Drafts</h2><a class="link small" href="{{ route('console.papers.index', ['status' => 'draft']) }}">See all</a></div>
    @forelse($drafts as $p)
        <a class="li" href="{{ route('console.papers.show', $p) }}" style="text-decoration:none;color:inherit">
            <div class="grow"><p class="h3">{{ $p->label() }}</p><p class="small muted">{{ $p->exam->name }} &middot; {{ $p->subject->name }} &middot; {{ $p->year }}</p></div>
            <span class="small muted">{{ $p->question_count }} {{ Str::plural('question', $p->question_count) }}</span><x-icon name="chevR" size="s"/>
        </a>
    @empty
        <div class="empty"><p class="h3">No drafts</p><p class="small muted">Create a paper to start adding questions.</p></div>
    @endforelse
</section>
@endsection
