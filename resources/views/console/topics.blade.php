@extends('layouts.app')

@section('title', 'Topics')
@section('page_class', 'mid')

@section('content')
<div class="col"><h1 class="h1">Topics</h1><p class="muted">Topics group questions inside a subject. Students see which ones they find hard, and can practise them.</p></div>

@if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

<form method="GET" class="filters"><div class="field" style="max-width:340px"><label for="subject">Subject</label>
    <select id="subject" name="subject" class="input" onchange="this.form.submit()">
        @foreach($subjects as $s)<option value="{{ $s->id }}" @selected($subject && $subject->id === $s->id)>{{ $s->name }} ({{ $s->topics_count }})</option>@endforeach
    </select></div><noscript><button class="btn btn-o btn-sm" type="submit">Show</button></noscript></form>

@if($subject)
<section class="card stack" style="gap:14px">
    <h2 class="h2">Add topics to {{ $subject->name }}</h2>
    <form method="POST" action="{{ route('console.topics.store') }}" class="stack" style="gap:12px">
        @csrf
        <input type="hidden" name="subject_id" value="{{ $subject->id }}">
        <div class="field"><label for="names">One topic per line</label>
            <textarea id="names" name="names" class="input" rows="4" placeholder="Concord&#10;Comprehension&#10;Vocabulary" required></textarea></div>
        <div><button class="btn btn-p btn-sm" type="submit">Add topics</button></div>
    </form>
</section>

<section class="card">
    <h2 class="h2" style="margin-bottom:4px">{{ $topics->count() }} {{ Str::plural('topic', $topics->count()) }} in {{ $subject->name }}</h2>
    @forelse($topics as $t)
        <div class="li" style="flex-wrap:wrap">
            <form method="POST" action="{{ route('console.topics.update', $t) }}" class="row grow" style="min-width:240px">@csrf @method('PUT')
                <input name="name" class="input" style="min-height:40px" value="{{ $t->name }}" maxlength="120" aria-label="Topic name">
                <button class="btn btn-o btn-sm" type="submit">Rename</button>
            </form>
            <span class="small muted">{{ $t->questions_count }} {{ Str::plural('question', $t->questions_count) }}</span>
            <form method="POST" action="{{ route('console.topics.destroy', $t) }}">@csrf @method('DELETE')
                <button class="btn btn-d btn-sm" type="submit" data-confirm="Delete the topic &quot;{{ $t->name }}&quot;?{{ $t->questions_count ? ' Its ' . $t->questions_count . ' questions will stay, without a topic.' : '' }}">Delete</button></form>
        </div>
    @empty
        <div class="empty"><p class="h3">No topics yet</p><p class="small muted">Add a few above, then tag questions with them.</p></div>
    @endforelse
</section>
@endif
@endsection
