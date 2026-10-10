@extends('layouts.app')

@section('title', 'Revision')
@section('page_class', 'mid')

@php $tones = ['wrong' => 'c-p', 'missed' => 'c-a', 'recent' => 'c-g', 'saved' => 'c-b']; $icons = ['wrong' => 'x', 'missed' => 'flag', 'recent' => 'clock', 'saved' => 'bookmark']; @endphp

@section('content')
<div class="col">
    <h1 class="h1">Revision</h1>
    <p class="muted">Go back over what you got wrong, and make your weak spots strong.</p>
</div>

<section class="card">
    @foreach($sources as $key => $source)
        @php $n = $counts[$key] ?? 0; @endphp
        <div class="li">
            <div class="sq {{ $tones[$key] }}"><x-icon name="{{ $icons[$key] }}"/></div>
            <div class="grow"><p class="h3">{{ $source['title'] }}</p><p class="small muted">{{ $source['text'] }}</p></div>
            <div class="row" style="gap:10px">
                <span class="badge {{ $n ? 'g' : 'neutral' }}">{{ $n }}</span>
                @if($n)
                    <a class="btn btn-o btn-sm" href="{{ $key === 'saved'
                        ? route('practice.session', ['saved' => 1, 'mode' => 'instant', 'count' => min(50, max(5, $n))])
                        : route('practice.session', ['revision' => $key, 'mode' => 'instant', 'count' => min(50, max(5, $n))]) }}">Revise</a>
                @endif
            </div>
        </div>
    @endforeach
</section>

@if($weakTopics->isNotEmpty())
<section class="card">
    <h2 class="h2" style="margin-bottom:4px">Weak topics</h2>
    @foreach($weakTopics as $t)
        <div class="li">
            <div class="grow col" style="gap:6px">
                <div class="row between"><span class="h3">{{ $t->topic }}</span><span class="small muted">{{ $t->subject }} &middot; {{ $t->accuracy }}%</span></div>
                <div class="bar amber"><i style="width:{{ $t->accuracy }}%"></i></div>
            </div>
            <a class="btn btn-o btn-sm" href="{{ route('practice.session', ['subject' => $t->subject_slug, 'topic' => $t->topic_id, 'mode' => 'instant', 'count' => 20]) }}">Practise</a>
        </div>
    @endforeach
</section>
@endif

@if($plan)
<section class="card">
    <h2 class="h2" style="margin-bottom:4px">Your next 7 days</h2>
    <p class="small muted" style="margin-bottom:6px">Built from the subjects you are weakest at. Starts today.</p>
    @foreach($plan as $d)
        <div class="li">
            <div class="sq {{ $d['today'] ? 'c-g' : '' }}">{{ $d['day'] }}</div>
            <div class="grow"><p class="h3">{{ $d['title'] }}</p><p class="small muted">{{ $d['text'] }}</p></div>
            <a class="btn {{ $d['today'] ? 'btn-p' : 'btn-o' }} btn-sm" href="{{ $d['url'] }}">{{ $d['today'] ? 'Start today' : 'Open' }}</a>
        </div>
    @endforeach
</section>
@endif
@endsection
