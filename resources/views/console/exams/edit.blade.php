@extends('layouts.app')

@section('title', $exam->name)
@section('page_class', 'mid')

@section('content')
<div class="col">
    <a class="link small" href="{{ route('console.exams.index') }}">&larr; Exams and prices</a>
    <h1 class="h1">{{ $exam->name }}</h1>
</div>

@if($errors->any())<div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('console.exams.update', $exam) }}" class="stack" style="gap:20px">
    @csrf @method('PUT')

    <section class="card stack" style="gap:14px">
        <h2 class="h2">Details</h2>
        <div class="grid2">
            <div class="field"><label for="name">Name</label><input id="name" name="name" class="input" value="{{ old('name', $exam->name) }}" maxlength="60" required></div>
            <div class="field"><label for="sort_order">Order on the site <span class="muted">(1 comes first)</span></label><input id="sort_order" name="sort_order" type="number" min="0" max="999" class="input" value="{{ old('sort_order', $exam->sort_order) }}"></div>
        </div>
        <div class="grid2">
            <div class="field"><label for="bundle_price">Price for all subjects together (₦) <span class="muted">(optional)</span></label>
                <input id="bundle_price" name="bundle_price" type="number" min="0" class="input" value="{{ old('bundle_price', $exam->bundle_price) }}" placeholder="Leave empty for no bundle">
                <span class="hint">Students see this as a saving when it is less than buying every subject one by one.</span></div>
            <label class="row" style="gap:10px;align-self:end;min-height:48px"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $exam->is_active))> Show this exam to students</label>
        </div>
    </section>

    <section class="card stack" style="gap:6px">
        <h2 class="h2">Subjects and prices</h2>
        <p class="small muted">Leave a price empty to use the site default ({{ \App\Services\Pricing::naira($defaults['price']) }}). A price of 0 makes that subject free. Leave free questions empty to use the default ({{ $defaults['free'] }}).</p>
        <div class="table-wrap" style="margin-top:8px">
            <table class="table">
                <thead><tr><th>Subject</th><th>Shown to students as</th><th>Price (₦)</th><th>Free questions</th><th title="Only used when the mock format below is customised">Mock questions</th><th></th></tr></thead>
                <tbody>
                @forelse($attached as $s)
                    @php $p = $s->pivot; @endphp
                    <tr>
                        <td><b>{{ $s->name }}</b></td>
                        <td><input class="input" style="min-height:40px" name="subjects[{{ $s->id }}][display_name]" value="{{ old("subjects.{$s->id}.display_name", $p->display_name) }}" maxlength="80" placeholder="{{ $s->name }}" aria-label="Display name for {{ $s->name }}"></td>
                        <td style="width:130px"><input class="input" style="min-height:40px" type="number" min="0" name="subjects[{{ $s->id }}][price]" value="{{ old("subjects.{$s->id}.price", $p->price) }}" placeholder="{{ $defaults['price'] }}" aria-label="Price for {{ $s->name }}"></td>
                        <td style="width:130px"><input class="input" style="min-height:40px" type="number" min="0" name="subjects[{{ $s->id }}][free_questions]" value="{{ old("subjects.{$s->id}.free_questions", $p->free_questions) }}" placeholder="{{ $defaults['free'] }}" aria-label="Free questions for {{ $s->name }}"></td>
                        <td style="width:130px"><input class="input" style="min-height:40px" type="number" min="5" max="200" name="subjects[{{ $s->id }}][mock_questions]" value="{{ old("subjects.{$s->id}.mock_questions", ($format['questions'][$s->slug] ?? null)) }}" placeholder="{{ $format['questions']['default'] ?? 50 }}" aria-label="Mock questions for {{ $s->name }}"></td>
                        <td><div class="actions"><button class="btn btn-d btn-sm" type="submit" form="detach-{{ $s->id }}" data-confirm="Remove {{ $s->name }} from {{ $exam->name }}?">Remove</button></div></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><div class="empty"><p class="small muted">No subjects yet. Add some below.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="card stack" style="gap:14px">
        <h2 class="h2">Mock exam</h2>
        <label class="row" style="gap:10px"><input type="checkbox" name="mock_custom" value="1" id="mock_custom" @checked(old('mock_custom', $customFormat))> Set how this exam's mock works</label>
        <p class="small muted" id="mock-note">{{ $customFormat ? 'Students take the mock below.' : 'Untick to use the standard format: ' . $format['subject_count'] . ' ' . \Illuminate\Support\Str::plural('subject', $format['subject_count']) . ', ' . ($format['questions']['default'] ?? 50) . ' questions each, ' . $format['minutes'] . ' minutes.' }} Each subject is marked out of 100. Every question is multiple choice.</p>
        <div id="mock-fields" class="stack" style="gap:14px">
            <div class="grid2">
                <div class="field"><label for="mock_label">Name of the mock</label><input id="mock_label" name="mock_label" class="input" maxlength="60" value="{{ old('mock_label', $format['label']) }}"></div>
                <div class="field"><label for="mock_subject_count">Subjects in one mock</label><input id="mock_subject_count" name="mock_subject_count" type="number" min="1" max="8" class="input" value="{{ old('mock_subject_count', $format['subject_count']) }}"></div>
            </div>
            <div class="grid3">
                <div class="field"><label for="mock_questions">Questions per subject</label><input id="mock_questions" name="mock_questions" type="number" min="5" max="200" class="input" value="{{ old('mock_questions', $format['questions']['default'] ?? 50) }}"></div>
                <div class="field"><label for="mock_minutes">Minutes in total</label><input id="mock_minutes" name="mock_minutes" type="number" min="5" max="300" class="input" value="{{ old('mock_minutes', $format['minutes']) }}"></div>
                <div class="field"><label for="mock_compulsory">Compulsory subject <span class="muted">(optional)</span></label>
                    <select id="mock_compulsory" name="mock_compulsory" class="input"><option value="">None</option>@foreach($attached as $s)<option value="{{ $s->slug }}" @selected(old('mock_compulsory', $format['compulsory'] ?? null) === $s->slug)>{{ $s->pivot->display_name ?: $s->name }}</option>@endforeach</select></div>
            </div>
            <p class="hint">A subject that should have a different number of questions (for example English in JAMB) gets its own number in the table above.</p>
        </div>
    </section>

    <div><button class="btn btn-p" type="submit">Save changes</button></div>
</form>

@foreach($attached as $s)
    <form id="detach-{{ $s->id }}" method="POST" action="{{ route('console.exams.detach', [$exam, $s]) }}">@csrf @method('DELETE')</form>
@endforeach

@if($deletable)
<form method="POST" action="{{ route('console.exams.destroy', $exam) }}">@csrf @method('DELETE')
    <button class="btn btn-d btn-sm" type="submit" data-confirm="Delete {{ $exam->name }} for good? Its subject list and prices go with it.">Delete this exam</button>
    <span class="small muted" style="margin-left:8px">Possible because it has no papers and nobody has ordered it.</span></form>
@endif

@if($available->isNotEmpty())
<form method="POST" action="{{ route('console.exams.attach', $exam) }}" class="card row wrap" style="gap:12px;align-items:flex-end">@csrf
    <div class="field grow" style="min-width:220px"><label for="subject_id">Add a subject to {{ $exam->name }}</label>
        <select id="subject_id" name="subject_id" class="input">@foreach($available as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
    <button class="btn btn-o" type="submit">Add subject</button>
</form>
@endif
@endsection

@push('scripts')
<script>
(function () {
  var box = document.getElementById('mock_custom'), fields = document.getElementById('mock-fields');
  if (!box || !fields) { return; }
  function sync() { fields.style.opacity = box.checked ? '' : '.5'; fields.querySelectorAll('input,select').forEach(function (el) { el.disabled = !box.checked; }); }
  box.addEventListener('change', sync); sync();
})();
</script>
@endpush