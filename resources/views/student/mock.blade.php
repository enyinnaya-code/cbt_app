@extends('layouts.app')

@section('title', 'Mock exam')
@section('page_class', 'mid')

@php
    $need = $format['subject_count'] ?? 1;
    $hasCompulsory = ! empty($format['compulsory']);
    $picks = $need - ($hasCompulsory ? 1 : 0);
    $totalQuestions = $format ? array_sum(array_map(fn ($o) => $o->planned, $options->take($need)->all())) : 0;
    $tones = ['c-a', 'c-b', 'c-p', 'c-g'];
    $unlocked = $options->where('locked', false);
    $lockedCompulsory = $options->contains(fn ($o) => $o->compulsory && $o->locked);
    // A mock needs whole subjects, so it cannot start on the free sample alone.
    $blocked = $options->count() >= $need && ($lockedCompulsory || $unlocked->count() < $need);
@endphp

@section('content')
<div class="col">
    <h1 class="h1">Mock exam</h1>
    <p class="muted">Timed like the real thing. Nothing is marked until you submit.</p>
</div>

@if($active)
    <div class="card primary row between wrap">
        <div>
            <p class="h3">You have a mock exam in progress</p>
            <p class="small" style="opacity:.9">It ends at {{ $active->deadline_at->format('g:i a') }}. The clock has kept running.</p>
        </div>
        <a class="btn btn-o" href="{{ route('mock.show', $active) }}">Resume exam</a>
    </div>
@endif

@if($exams->isEmpty())
    <div class="card empty"><p class="h3">No exams are set up yet</p></div>
@else
    <div class="seg" role="tablist" aria-label="Exam">
        @foreach($exams as $e)
            <a href="{{ route('mock.index', ['exam' => $e->slug]) }}" class="{{ $exam && $exam->id === $e->id ? 'on' : '' }}" role="tab">{{ $e->name }}</a>
        @endforeach
    </div>

    @if($options->count() < $need)
        <div class="card empty">
            <span class="sq c-a"><x-icon name="clock"/></span>
            <p class="h3">The {{ $exam->name }} mock is not ready yet</p>
            <p class="small muted">It needs {{ $need }} {{ Str::plural('subject', $need) }} with enough questions. Only {{ $options->count() }} {{ $options->count() === 1 ? 'has' : 'have' }} them so far. You can still use Practice.</p>
            <a class="btn btn-p btn-sm" href="{{ route('practice.index', ['exam' => $exam->slug]) }}">Go to Practice</a>
        </div>
    @else
        <div class="card flat row wrap" style="gap:24px">
            <div><p class="tiny muted">Format</p><p class="h3">{{ $format['label'] }}</p></div>
            <div><p class="tiny muted">Questions</p><p class="h3">{{ $totalQuestions }}</p></div>
            <div><p class="tiny muted">Time</p><p class="h3">{{ intdiv($format['minutes'], 60) ? intdiv($format['minutes'], 60) . ' h' : '' }} {{ $format['minutes'] % 60 ? $format['minutes'] % 60 . ' min' : '' }}</p></div>
            <div><p class="tiny muted">Scored out of</p><p class="h3">{{ 100 * $need }}</p></div>
        </div>

        @if($blocked)
            <div class="card row between wrap" style="background:var(--accent-soft);border:0">
                <div><p class="h3">Unlock your subjects to take this mock</p>
                    <p class="small">A mock exam uses every question in a subject, so the subjects you pick must be unlocked.{{ $lockedCompulsory ? ' ' . $options->firstWhere('compulsory', true)->name . ' is needed for every ' . $exam->name . ' mock.' : '' }}</p></div>
                <a class="btn btn-p" href="{{ route('checkout.show', ['exam' => $exam->slug]) }}"><x-icon name="lock" size="s"/>Unlock subjects</a>
            </div>
        @endif

        <form method="POST" action="{{ route('mock.start') }}" class="stack" style="gap:22px" id="mock-form" data-need="{{ $picks }}" data-blocked="{{ $blocked ? 1 : 0 }}">
            @csrf
            <input type="hidden" name="exam" value="{{ $exam->slug }}">
            @error('subjects')<div class="alert err" role="alert">{{ $message }}</div>@enderror

            <section class="col" style="gap:12px">
                <h2 class="h3">{{ $need === 1 ? 'Choose a subject' : ($hasCompulsory ? 'Choose ' . $picks . ' more ' . Str::plural('subject', $picks) : 'Choose ' . $need . ' subjects') }}</h2>
                <div class="grid3">
                    @foreach($options as $i => $s)
                        @if($s->locked)
                        <a class="qa" href="{{ route('checkout.show', ['exam' => $exam->slug, 'subjects' => [$s->slug]]) }}" style="opacity:.85">
                            <span class="row between"><span class="sq {{ $tones[$i % 4] }}">{{ $s->code }}</span><span class="badge neutral"><x-icon name="lock" size="s"/>Locked</span></span>
                            <span><span class="h3" style="display:block">{{ $s->name }}</span><span class="small muted">Unlock for {{ \App\Services\Pricing::naira($s->price) }}</span></span>
                        </a>
                        @continue
                        @endif
                        <label class="qa" style="cursor:{{ $s->compulsory ? 'default' : 'pointer' }}">
                            <span class="row between">
                                <span class="sq {{ $tones[$i % 4] }}">{{ $s->code }}</span>
                                @if($s->compulsory)
                                    <span class="badge g">Compulsory</span>
                                    <input type="hidden" name="subjects[]" value="{{ $s->slug }}">
                                @else
                                    <input type="{{ $need === 1 ? 'radio' : 'checkbox' }}" name="subjects[]" value="{{ $s->slug }}" class="pick" @checked(in_array($s->slug, old('subjects', [])))>
                                @endif
                            </span>
                            <span>
                                <span class="h3" style="display:block">{{ $s->name }}</span>
                                <span class="small muted">{{ min($s->planned, $s->available) }} questions{{ $s->available < $s->planned ? ', all we have' : '' }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p class="hint">If a subject has fewer questions than the real exam, the mock is shorter and the clock is shortened to match.</p>
            </section>

            <div class="row wrap">
                <button class="btn btn-p" type="submit" id="mock-start" @disabled($blocked)>Start mock exam</button>
                <span class="small muted" id="mock-hint"></span>
            </div>
        </form>
    @endif

    @if($recent->isNotEmpty())
    <section class="card">
        <h2 class="h2" style="margin-bottom:4px">Your recent mocks</h2>
        @foreach($recent as $r)
            <a class="li" href="{{ route('mock.result', $r) }}" style="text-decoration:none;color:inherit">
                <div class="grow"><p class="h3">{{ $r->exam->name }} mock</p><p class="small muted">{{ $r->submitted_at->format('j F Y g:i a') }}</p></div>
                <span class="h3">{{ $r->score }} / {{ $r->total }}</span><x-icon name="chevR" size="s"/>
            </a>
        @endforeach
    </section>
    @endif
@endif
@endsection

@push('scripts')
<script>
(function () {
  var form = document.getElementById('mock-form');
  if (!form) { return; }
  var need = parseInt(form.dataset.need, 10);
  var boxes = Array.prototype.slice.call(form.querySelectorAll('input.pick'));
  var start = document.getElementById('mock-start');
  var hint = document.getElementById('mock-hint');

  function sync() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    if (boxes.length && boxes[0].type === 'checkbox') {
      boxes.forEach(function (b) { b.disabled = !b.checked && n >= need; });
    }
    var blocked = form.dataset.blocked === '1';
    start.disabled = blocked || n !== need;
    start.style.opacity = start.disabled ? '.5' : '';
    hint.textContent = blocked || n === need ? '' : 'Pick ' + (need - n) + ' more.';
  }
  boxes.forEach(function (b) { b.addEventListener('change', sync); });
  sync();
})();
</script>
@endpush
