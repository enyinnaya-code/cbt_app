@extends('layouts.app')

@section('title', 'Old tests')
@section('page_class', 'mid')

@section('content')
<div class="col">
    <h1 class="h1">Old tests</h1>
    <p class="muted">These came from before TestaCBT. Give each one an exam, subject and year and it becomes a draft paper you can check and publish. Questions are moved, not copied.</p>
</div>

@if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

@forelse($tests as $t)
    <form method="POST" action="{{ route('console.legacy.store', $t) }}" class="card stack" style="gap:14px">
        @csrf
        <div class="row between wrap">
            <div><p class="h3">{{ $t->test_name }}</p><p class="small muted">Old test #{{ $t->id }} &middot; {{ $t->question_count }} {{ Str::plural('question', $t->question_count) }}</p></div>
        </div>
        @if($t->question_count > 0)
        <div class="filters">
            <div class="field"><label for="exam-{{ $t->id }}">Exam</label>
                <select id="exam-{{ $t->id }}" name="exam_id" class="input" required data-exam-for="{{ $t->id }}"><option value="">Choose</option>@foreach($exams as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></div>
            <div class="field"><label for="subject-{{ $t->id }}">Subject</label>
                <select id="subject-{{ $t->id }}" name="subject_id" class="input" required><option value="">Choose</option>@foreach($subjects as $s)<option value="{{ $s->id }}" data-exams="{{ $s->exams->pluck('id')->implode(',') }}">{{ $s->name }}</option>@endforeach</select></div>
            <div class="field" style="max-width:120px"><label for="year-{{ $t->id }}">Year</label><input id="year-{{ $t->id }}" name="year" type="number" min="1970" max="{{ date('Y') + 1 }}" class="input" required></div>
            <div><button class="btn btn-p btn-sm" type="submit" data-confirm="Create a draft paper from &quot;{{ $t->test_name }}&quot;?">Create paper</button></div>
        </div>
        @else
            <p class="small muted">This test has no questions, so there is nothing to move.</p>
        @endif
    </form>
@empty
    <div class="card empty"><span class="sq c-g"><x-icon name="check"/></span><p class="h3">All done</p><p class="small muted">Every old test has been turned into a paper.</p></div>
@endforelse
@endsection

@push('scripts')
<script>
// Show only the subjects the chosen exam offers, on each row.
document.querySelectorAll('select[data-exam-for]').forEach(function (exam) {
  var subject = document.getElementById('subject-' + exam.dataset.examFor);
  function filter() {
    Array.prototype.forEach.call(subject.options, function (o) {
      if (!o.value) { return; }
      var ok = !exam.value || (',' + o.dataset.exams + ',').indexOf(',' + exam.value + ',') > -1;
      o.hidden = !ok; o.disabled = !ok;
      if (!ok && o.selected) { subject.value = ''; }
    });
  }
  exam.addEventListener('change', filter);
});
</script>
@endpush
