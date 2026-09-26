@extends('layouts.app')

@section('title', 'Papers')

@section('content')
<div class="row between wrap">
    <div class="col"><h1 class="h1">Papers</h1><p class="muted">Each paper is one past exam: an exam, a subject and a year.</p></div>
    <a class="btn btn-p" href="{{ route('console.papers.create') }}"><x-icon name="plus" size="s"/>New paper</a>
</div>

<form method="GET" class="filters card" style="padding:14px">
    <div class="field"><label for="f-exam">Exam</label>
        <select id="f-exam" name="exam" class="input"><option value="">All</option>@foreach($exams as $e)<option value="{{ $e->id }}" @selected(($filters['exam'] ?? null) == $e->id)>{{ $e->name }}</option>@endforeach</select></div>
    <div class="field"><label for="f-subject">Subject</label>
        <select id="f-subject" name="subject" class="input"><option value="">All</option>@foreach($subjects as $s)<option value="{{ $s->id }}" @selected(($filters['subject'] ?? null) == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
    <div class="field"><label for="f-status">Status</label>
        <select id="f-status" name="status" class="input"><option value="">All</option>@foreach(['draft' => 'Draft', 'published' => 'Published'] as $k => $v)<option value="{{ $k }}" @selected(($filters['status'] ?? null) === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field" style="max-width:110px"><label for="f-year">Year</label><input id="f-year" name="year" class="input" type="number" min="1970" value="{{ $filters['year'] ?? '' }}"></div>
    <div class="field"><label for="f-q">Search title</label><input id="f-q" name="q" class="input" value="{{ $filters['q'] ?? '' }}"></div>
    <div class="row"><button class="btn btn-o btn-sm" type="submit">Filter</button>@if(array_filter($filters))<a class="btn btn-t btn-sm" href="{{ route('console.papers.index') }}">Clear</a>@endif</div>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Paper</th><th>Exam</th><th>Subject</th><th>Year</th><th>Questions</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse($papers as $p)
            <tr>
                <td><a class="link" href="{{ route('console.papers.show', $p) }}">{{ $p->label() }}</a>@if($p->legacy_test_id)<span class="badge neutral" style="margin-left:6px">old test</span>@endif</td>
                <td>{{ $p->exam->name }}</td>
                <td>{{ $p->subject->name }}</td>
                <td>{{ $p->year }}</td>
                <td>{{ $p->question_count }}</td>
                <td><span class="badge {{ $p->status === 'published' ? 'g' : '' }}">{{ ucfirst($p->status) }}</span></td>
                <td><div class="actions"><a class="btn btn-o btn-sm" href="{{ route('console.papers.show', $p) }}">Open</a></div></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty"><p class="h3">No papers match</p><p class="small muted">Try clearing the filters, or create a new paper.</p></div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $papers->links('vendor.pagination.testacbt') }}
@endsection
