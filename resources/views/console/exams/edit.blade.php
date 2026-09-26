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
                <thead><tr><th>Subject</th><th>Shown to students as</th><th>Price (₦)</th><th>Free questions</th><th></th></tr></thead>
                <tbody>
                @forelse($attached as $s)
                    @php $p = $s->pivot; @endphp
                    <tr>
                        <td><b>{{ $s->name }}</b></td>
                        <td><input class="input" style="min-height:40px" name="subjects[{{ $s->id }}][display_name]" value="{{ old("subjects.{$s->id}.display_name", $p->display_name) }}" maxlength="80" placeholder="{{ $s->name }}" aria-label="Display name for {{ $s->name }}"></td>
                        <td style="width:130px"><input class="input" style="min-height:40px" type="number" min="0" name="subjects[{{ $s->id }}][price]" value="{{ old("subjects.{$s->id}.price", $p->price) }}" placeholder="{{ $defaults['price'] }}" aria-label="Price for {{ $s->name }}"></td>
                        <td style="width:130px"><input class="input" style="min-height:40px" type="number" min="0" name="subjects[{{ $s->id }}][free_questions]" value="{{ old("subjects.{$s->id}.free_questions", $p->free_questions) }}" placeholder="{{ $defaults['free'] }}" aria-label="Free questions for {{ $s->name }}"></td>
                        <td><div class="actions"><button class="btn btn-d btn-sm" type="submit" form="detach-{{ $s->id }}" data-confirm="Remove {{ $s->name }} from {{ $exam->name }}?">Remove</button></div></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><div class="empty"><p class="small muted">No subjects yet. Add some below.</p></div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div><button class="btn btn-p" type="submit">Save changes</button></div>
</form>

@foreach($attached as $s)
    <form id="detach-{{ $s->id }}" method="POST" action="{{ route('console.exams.detach', [$exam, $s]) }}">@csrf @method('DELETE')</form>
@endforeach

@if($available->isNotEmpty())
<form method="POST" action="{{ route('console.exams.attach', $exam) }}" class="card row wrap" style="gap:12px;align-items:flex-end">@csrf
    <div class="field grow" style="min-width:220px"><label for="subject_id">Add a subject to {{ $exam->name }}</label>
        <select id="subject_id" name="subject_id" class="input">@foreach($available as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div>
    <button class="btn btn-o" type="submit">Add subject</button>
</form>
@endif
@endsection
