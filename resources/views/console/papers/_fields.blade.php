{{-- Shared by "New paper" and the details editor on a paper. Expects $paper, $exams, $subjects. --}}
<div class="grid2">
    <div class="field">
        <label for="exam_id">Exam</label>
        <select id="exam_id" name="exam_id" class="input @error('exam_id') err @enderror" required>
            <option value="">Choose an exam</option>
            @foreach($exams as $e)<option value="{{ $e->id }}" @selected((string) old('exam_id', $paper->exam_id) === (string) $e->id)>{{ $e->name }}</option>@endforeach
        </select>
        @error('exam_id')<span class="errtext">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="subject_id">Subject</label>
        <select id="subject_id" name="subject_id" class="input @error('subject_id') err @enderror" required>
            <option value="">Choose a subject</option>
            @foreach($subjects as $s)<option value="{{ $s->id }}" data-exams="{{ $s->exams->pluck('id')->implode(',') }}" @selected((string) old('subject_id', $paper->subject_id) === (string) $s->id)>{{ $s->name }}</option>@endforeach
        </select>
        @error('subject_id')<span class="errtext">{{ $message }}</span>@enderror
    </div>
</div>
<div class="grid3">
    <div class="field">
        <label for="year">Year</label>
        <input id="year" name="year" type="number" min="1970" max="{{ date('Y') + 1 }}" class="input @error('year') err @enderror" value="{{ old('year', $paper->year) }}" required>
        @error('year')<span class="errtext">{{ $message }}</span>@enderror
    </div>
    <div class="field">
        <label for="title">Title <span class="muted">(optional)</span></label>
        <input id="title" name="title" class="input" maxlength="160" value="{{ old('title', $paper->title) }}" placeholder="Defaults to exam, subject and year">
    </div>
    <div class="field">
        <label for="duration_minutes">Minutes <span class="muted">(optional)</span></label>
        <input id="duration_minutes" name="duration_minutes" type="number" min="1" max="300" class="input @error('duration_minutes') err @enderror" value="{{ old('duration_minutes', $paper->duration_minutes) }}">
    </div>
</div>

@once
@push('scripts')
<script>
// Only offer the subjects the chosen exam actually has.
(function () {
  var exam = document.getElementById('exam_id'), subject = document.getElementById('subject_id');
  if (!exam || !subject) { return; }
  function filter() {
    Array.prototype.forEach.call(subject.options, function (o) {
      if (!o.value) { return; }
      var ok = !exam.value || (',' + o.dataset.exams + ',').indexOf(',' + exam.value + ',') > -1;
      o.hidden = !ok; o.disabled = !ok;
      if (!ok && o.selected) { subject.value = ''; }
    });
  }
  exam.addEventListener('change', filter);
  filter();
})();
</script>
@endpush
@endonce
