@extends('layouts.app')

@section('title', 'Exams and prices')
@section('page_class', 'mid')

@section('content')
<div class="col"><h1 class="h1">Exams and prices</h1>
    <p class="muted">The exams students can practise, the subjects each one offers, and what a subject costs. New subjects are charged {{ \App\Services\Pricing::naira($defaults['price']) }} unless you set a price, and every subject has {{ $defaults['free'] }} free questions. <a class="link" href="{{ route('console.settings.edit') }}">Change these defaults</a>.</p></div>

@if($errors->any())<div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Exam</th><th>Status</th><th>Subjects</th><th>Papers</th><th>Bundle price</th><th></th></tr></thead>
        <tbody>
        @foreach($exams as $e)
            <tr>
                <td><a class="link" href="{{ route('console.exams.edit', $e) }}">{{ $e->name }}</a></td>
                <td><span class="badge {{ $e->is_active ? 'g' : 'neutral' }}">{{ $e->is_active ? 'Shown' : 'Hidden' }}</span></td>
                <td>{{ $e->subjects_count }}</td>
                <td>{{ $e->papers_count }}</td>
                <td>{{ $e->bundle_price !== null ? \App\Services\Pricing::naira($e->bundle_price) : 'None' }}</td>
                <td><div class="actions"><a class="btn btn-o btn-sm" href="{{ route('console.exams.edit', $e) }}">Edit</a></div></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<div class="grid2">
    <form method="POST" action="{{ route('console.exams.store') }}" class="card stack" style="gap:12px">@csrf
        <h2 class="h2">Add an exam type</h2>
        <p class="small muted">For example IELTS or GRE. It appears for students straight away, in Practice, Mock exam, Pricing and on the home page, once it has subjects and published papers.</p>
        <div class="field"><label for="exam-name">Name</label><input id="exam-name" name="name" class="input" maxlength="60" required placeholder="e.g. Common Entrance"></div>
        <div><button class="btn btn-p btn-sm" type="submit">Add exam</button></div>
    </form>
    <form method="POST" action="{{ route('console.subjects.store') }}" class="card stack" style="gap:12px">@csrf
        <h2 class="h2">Add a subject</h2>
        <div class="grid2 keep">
            <div class="field"><label for="subject-name">Name</label><input id="subject-name" name="name" class="input" maxlength="80" required placeholder="e.g. Further Mathematics"></div>
            <div class="field"><label for="subject-code">Short code</label><input id="subject-code" name="code" class="input" maxlength="4" placeholder="FM"></div>
        </div>
        <div class="field"><label for="subject-exam">Add it to an exam <span class="muted">(optional)</span></label>
            <select id="subject-exam" name="exam_id" class="input"><option value="">Not yet</option>@foreach($exams as $e)<option value="{{ $e->id }}">{{ $e->name }}</option>@endforeach</select></div>
        <div><button class="btn btn-p btn-sm" type="submit">Add subject</button></div>
    </form>
</div>
@endsection
