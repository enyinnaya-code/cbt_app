@extends('layouts.app')

@section('title', 'Saved')
@section('page_class', 'mid')

@section('content')
<div class="row between wrap">
    <div class="col">
        <h1 class="h1">Saved</h1>
        <p class="muted">Questions you marked to come back to.</p>
    </div>
    @if($questions->isNotEmpty())
        <a class="btn btn-p" href="{{ route('practice.session', ['saved' => 1, 'mode' => 'instant', 'count' => min(50, max(5, $questions->count()))]) }}">Practise these</a>
    @endif
</div>

@forelse($questions as $q)
    <article class="card stack" style="gap:14px">
        <div class="row between wrap">
            <span class="badge">{{ $q->exam }} {{ $q->year }} &middot; {{ $q->subject }}</span>
            <form method="POST" action="{{ route('saved.toggle') }}">@csrf
                <input type="hidden" name="question_id" value="{{ $q->id }}"><input type="hidden" name="bookmarked" value="0">
                <button class="btn btn-o btn-sm" type="submit"><x-icon name="trash" size="s"/>Remove</button>
            </form>
        </div>
        <div class="qtext prose">{!! $q->question !!}</div>
        <details>
            <summary class="link" style="cursor:pointer">Show answer</summary>
            <div class="stack" style="gap:8px;margin-top:12px">
                @foreach($q->options as $letter => $text)
                    <div class="opt {{ $letter === $q->answer ? 'correct' : '' }}" style="cursor:default"><span class="bub">{{ $letter }}</span><span>{!! $text !!}</span>@if($letter === $q->answer)<x-icon name="check" class="end"/>@endif</div>
                @endforeach
                @php $exp = $lang === 'pcm' ? ($q->explanation_pcm ?: $q->explanation_en) : ($q->explanation_en ?: $q->explanation_pcm); @endphp
                @if($exp)<div class="card flat prose"><p class="h3" style="margin-bottom:6px">Explanation</p>{!! $exp !!}</div>@endif
            </div>
        </details>
    </article>
@empty
    <div class="card empty">
        <span class="sq c-b"><x-icon name="bookmark"/></span>
        <p class="h3">Nothing saved yet</p>
        <p class="small muted">While you practise, tap the bookmark on a question to keep it here.</p>
        <a class="btn btn-p btn-sm" href="{{ route('practice.index') }}">Start practising</a>
    </div>
@endforelse
@endsection
