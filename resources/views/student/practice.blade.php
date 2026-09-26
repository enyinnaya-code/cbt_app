@extends('layouts.app')

@section('title', 'Practice')
@section('page_class', 'mid')

@php $tones = ['c-a', 'c-b', 'c-p', 'c-g']; @endphp

@section('content')
<div class="col">
    <h1 class="h1">Practice</h1>
    <p class="muted">Pick what you want to practise. You see the right answer as you go.</p>
</div>

@if($exams->isEmpty())
    <div class="card empty"><p class="h3">No exams are set up yet</p></div>
@else
    <div class="seg" role="tablist" aria-label="Exam">
        @foreach($exams as $e)
            <a href="{{ route('practice.index', ['exam' => $e->slug]) }}" class="{{ $exam && $exam->id === $e->id ? 'on' : '' }}" role="tab" aria-selected="{{ $exam && $exam->id === $e->id ? 'true' : 'false' }}">{{ $e->name }}</a>
        @endforeach
    </div>

    @if($subjects->isEmpty())
        <div class="card empty">
            <span class="sq c-a"><x-icon name="book"/></span>
            <p class="h3">No {{ $exam->name }} questions yet</p>
            <p class="small muted">Questions are added by our examiners. Try another exam, or check back soon.</p>
        </div>
    @else
        <section class="col" style="gap:12px">
            <h2 class="h3">Subject</h2>
            <div class="grid3">
                @foreach($subjects as $i => $s)
                    <a href="{{ route('practice.index', ['exam' => $exam->slug, 'subject' => $s->slug]) }}"
                       class="qa {{ $subject && $subject->id === $s->id ? 'sel' : '' }}">
                        <span class="row between"><span class="sq {{ $tones[$i % 4] }}">{{ $s->code }}</span>@if($subject && $subject->id === $s->id)<x-icon name="check" size="s"/>@endif</span>
                        <span><span class="h3" style="display:block">{{ $s->label }}</span><span class="small muted">{{ number_format($s->available) }} {{ Str::plural('question', $s->available) }}</span></span>
                    </a>
                @endforeach
            </div>
        </section>

        @if($subject)
            <form method="GET" action="{{ route('practice.session') }}" class="stack" style="gap:24px">
                <input type="hidden" name="exam" value="{{ $exam->slug }}">
                <input type="hidden" name="subject" value="{{ $subject->slug }}">

                <section class="col" style="gap:10px">
                    <h2 class="h3">Year</h2>
                    <div class="chips">
                        <label class="chip"><input type="radio" name="year" value="" checked>All years</label>
                        @foreach($years as $y)
                            <label class="chip"><input type="radio" name="year" value="{{ $y }}">{{ $y }}</label>
                        @endforeach
                    </div>
                    <p class="hint">One year keeps the questions in the order of the real paper. All years mixes them up.</p>
                </section>

                @if($topics->isNotEmpty())
                <section class="col" style="gap:10px">
                    <h2 class="h3">Topic <span class="muted small" style="font-weight:500">(optional)</span></h2>
                    <select name="topic" class="input">
                        <option value="">Any topic</option>
                        @foreach($topics as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                    </select>
                </section>
                @endif

                <section class="col" style="gap:10px">
                    <h2 class="h3">How do you want to practise?</h2>
                    <div class="seg">
                        <label><input type="radio" name="mode" value="instant" checked>See answer now</label>
                        <label><input type="radio" name="mode" value="end">See answers at the end</label>
                    </div>
                </section>

                <section class="col" style="gap:10px">
                    <h2 class="h3">Number of questions</h2>
                    <div class="chips">
                        @foreach($counts as $c)
                            <label class="chip"><input type="radio" name="count" value="{{ $c }}" @checked($c === 20)>{{ $c }}</label>
                        @endforeach
                    </div>
                    <p class="hint">If there are fewer questions than this, you get all of them.</p>
                </section>

                <div><button class="btn btn-p" type="submit">Start practice</button></div>
            </form>
        @else
            <p class="muted small">Choose a subject to continue.</p>
        @endif
    @endif

    @if(Route::has('saved'))
        <p class="small muted">Want to go back over questions you saved? <a class="link" href="{{ route('saved') }}">Open Saved</a></p>
    @endif
@endif
@endsection
