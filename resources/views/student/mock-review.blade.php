@extends('layouts.app')

@section('title', 'Review answers')
@section('page_class', 'mid')

@section('content')
<div class="row between wrap">
    <div><p class="small muted">Mock exam</p><h1 class="h1">Review answers</h1></div>
    <a class="btn btn-o" href="{{ route('mock.result', $run) }}">Back to result</a>
</div>

@foreach($sections as $section)
    <h2 class="h2" style="margin-top:8px">{{ $section['name'] }}</h2>

    @foreach($section['questions'] as $n => $q)
        @php
            $picked = $answers[$q['id']] ?? null;
            $ok = $picked === $q['answer'];
            $exp = $lang === 'pcm' ? ($q['explanation_pcm'] ?? $q['explanation_en']) : ($q['explanation_en'] ?? $q['explanation_pcm']);
        @endphp
        <article class="card stack" style="gap:14px">
            <div class="row between wrap">
                <span class="h3">Question {{ $n + 1 }}</span>
                <span class="badge {{ $ok ? 'g' : ($picked ? 'r' : 'n') }}">{{ $ok ? 'Correct' : ($picked ? 'Wrong' : 'Not answered') }}</span>
            </div>
            @if($q['passage_id'] && isset($passages[$q['passage_id']]))
                <div class="card flat prose">{!! $passages[$q['passage_id']] !!}</div>
            @endif
            <div class="qtext prose">{!! $q['html'] !!}</div>
            <div class="stack" style="gap:8px">
                @foreach($q['options'] as $letter => $text)
                    <div class="opt {{ $letter === $q['answer'] ? 'correct' : ($letter === $picked ? 'wrong' : '') }}" style="cursor:default">
                        <span class="bub">{{ $letter }}</span><span>{!! $text !!}</span>
                        @if($letter === $q['answer'])<x-icon name="check" class="end"/>@elseif($letter === $picked)<x-icon name="x" class="end"/>@endif
                    </div>
                @endforeach
            </div>
            @if($exp)<div class="card flat prose"><p class="h3" style="margin-bottom:6px">Explanation</p>{!! $exp !!}</div>@endif
        </article>
    @endforeach
@endforeach
@endsection
