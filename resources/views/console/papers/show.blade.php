@extends('layouts.app')

@section('title', $paper->label())
@section('page_class', 'mid')

@php $isAdmin = auth()->user()->isAdmin(); @endphp

@section('content')
<div class="col">
    <a class="link small" href="{{ route('console.papers.index') }}">&larr; Papers</a>
    <div class="row between wrap">
        <div>
            <h1 class="h1">{{ $paper->label() }}</h1>
            <div class="row wrap" style="gap:8px;margin-top:8px">
                <span class="badge {{ $paper->status === 'published' ? 'g' : '' }}">{{ ucfirst($paper->status) }}</span>
                <span class="badge neutral">{{ $paper->exam->name }}</span><span class="badge neutral">{{ $paper->subject->name }}</span><span class="badge neutral">{{ $paper->year }}</span>
                <span class="small muted">{{ $questionCount }} {{ Str::plural('question', $questionCount) }}@if($attempts) &middot; {{ number_format($attempts) }} student {{ Str::plural('answer', $attempts) }}@endif</span>
            </div>
        </div>
        @if($isAdmin)
            <div class="row wrap">
                @if($paper->status === 'draft')
                    <form method="POST" action="{{ route('console.papers.publish', $paper) }}">@csrf<button class="btn btn-p" type="submit" data-confirm="Publish this paper? Students will see it straight away.">Publish</button></form>
                @else
                    <form method="POST" action="{{ route('console.papers.unpublish', $paper) }}">@csrf<button class="btn btn-o" type="submit" data-confirm="Move this paper back to draft? Students will stop seeing it.">Unpublish</button></form>
                @endif
            </div>
        @endif
    </div>
</div>

@unless($editable)
    <div class="alert info">This paper is live, so only an admin can change it. Ask an admin to unpublish it first if it needs editing.</div>
@endunless
@if($paper->status === 'draft' && ! $isAdmin)
    <div class="alert info">Students cannot see this paper yet. When it is ready, ask an admin to publish it.</div>
@endif
@if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

@if($editable)
    <div class="row wrap">
        <a class="btn btn-p" href="{{ route('console.questions.create', $paper) }}"><x-icon name="plus" size="s"/>Add question</a>
        <a class="btn btn-o" href="{{ route('console.questions.create', [$paper, 'type' => 'passage']) }}">Add passage or instruction</a>
        <a class="btn btn-o" href="{{ route('console.import.create', $paper) }}"><x-icon name="upload" size="s"/>Import from CSV</a>
    </div>
@endif

@forelse($items as $q)
    <article class="item {{ $q->not_question ? 'passage' : '' }}" id="q{{ $q->id }}">
        <span class="num" title="{{ $q->not_question ? 'Passage or instruction' : 'Question ' . $q->number }}">{{ $q->not_question ? 'P' : $q->number }}</span>
        <div class="body">
            @if($q->not_question)<span class="badge neutral" style="margin-bottom:6px">Passage or instruction</span>@endif
            <div class="prose">{!! \App\Support\HtmlCleaner::clean($q->question) !!}</div>
            @unless($q->not_question)
                <div class="opts-mini">
                    @foreach($q->option_list as $letter => $text)
                        <span class="{{ $letter === strtoupper(trim((string) $q->answer)) ? 'key' : '' }}">{{ $letter }}. {{ \Illuminate\Support\Str::limit(html_entity_decode(strip_tags((string) $text)), 40) ?: '(image)' }}</span>
                    @endforeach
                    @if($q->topic)<span class="badge">{{ $q->topic->name }}</span>@endif
                    @if(! $q->explanation_en && ! $q->explanation_pcm)<span class="badge neutral">no explanation</span>@endif
                    @if(! isset($q->option_list[strtoupper(trim((string) $q->answer))]))<span class="badge r">no valid answer</span>@endif
                </div>
            @endunless
        </div>
        @if($editable && $q->paper_id)
            <div class="col" style="gap:6px">
                <a class="btn btn-o btn-sm" href="{{ route('console.questions.edit', $q) }}">Edit</a>
                <form method="POST" action="{{ route('console.questions.destroy', $q) }}">@csrf @method('DELETE')
                    <button class="btn btn-d btn-sm" type="submit" data-confirm="Delete this {{ $q->not_question ? 'passage' : 'question' }}?">Delete</button></form>
            </div>
        @endif
    </article>
@empty
    <div class="card empty">
        <span class="sq c-g"><x-icon name="file"/></span>
        <p class="h3">No questions yet</p>
        <p class="small muted">Add them one at a time, or import a whole paper from a CSV file.</p>
    </div>
@endforelse

@if($editable)
<details class="card">
    <summary class="h3" style="cursor:pointer">Paper details</summary>
    <form method="POST" action="{{ route('console.papers.update', $paper) }}" class="stack" style="gap:18px;margin-top:16px">
        @csrf @method('PUT')
        @include('console.papers._fields')
        <div><button class="btn btn-o" type="submit">Save details</button></div>
    </form>
</details>

@if($paper->status === 'draft')
<form method="POST" action="{{ route('console.papers.destroy', $paper) }}">@csrf @method('DELETE')
    <button class="btn btn-d" type="submit" data-confirm="Delete this whole paper and all its questions? This cannot be undone.">Delete paper</button>
</form>
@endif
@endif
@endsection
