@extends('layouts.app')

@section('title', $event->exists ? 'Edit event' : 'New event')
@section('page_class', 'narrow')

@section('content')
<div class="col">
    <a class="link small" href="{{ route('console.events.index') }}">&larr; Events</a>
    <h1 class="h1">{{ $event->exists ? 'Edit event' : 'New event' }}</h1>
</div>

@if($errors->any())<div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ $event->exists ? route('console.events.update', $event) : route('console.events.store') }}" class="card stack" style="gap:16px">
    @csrf @if($event->exists) @method('PUT') @endif
    <div class="field"><label for="title">Title</label><input id="title" name="title" class="input" value="{{ old('title', $event->title) }}" maxlength="160" required placeholder="e.g. JAMB UTME 2027 registration closes"></div>
    <div class="grid2">
        <div class="field"><label for="kind">Type</label><select id="kind" name="kind" class="input">@foreach(\App\Models\Event::kinds() as $k => $label)<option value="{{ $k }}" @selected(old('kind', $event->kind) === $k)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="exam_id">Exam <span class="muted">(optional)</span></label><select id="exam_id" name="exam_id" class="input"><option value="">None</option>@foreach($exams as $x)<option value="{{ $x->id }}" @selected((string) old('exam_id', $event->exam_id) === (string) $x->id)>{{ $x->name }}</option>@endforeach</select></div>
    </div>
    <div class="grid2">
        <div class="field"><label for="starts_on">Starts</label><input id="starts_on" name="starts_on" type="date" class="input" value="{{ old('starts_on', $event->starts_on?->format('Y-m-d')) }}" required></div>
        <div class="field"><label for="ends_on">Ends <span class="muted">(optional)</span></label><input id="ends_on" name="ends_on" type="date" class="input" value="{{ old('ends_on', $event->ends_on?->format('Y-m-d')) }}"></div>
    </div>
    <div class="field"><label for="location">Where <span class="muted">(optional)</span></label><input id="location" name="location" class="input" value="{{ old('location', $event->location) }}" maxlength="160" placeholder="e.g. Nationwide, or Online"></div>
    <div class="field"><label for="description">Short note <span class="muted">(optional)</span></label><textarea id="description" name="description" class="input" rows="3" maxlength="500" style="padding:12px 14px">{{ old('description', $event->description) }}</textarea></div>
    <div class="field"><label for="link_url">Link <span class="muted">(optional)</span></label><input id="link_url" name="link_url" type="url" class="input" value="{{ old('link_url', $event->link_url) }}" maxlength="500" placeholder="https://"></div>
    <div class="field"><label for="status">Status</label><select id="status" name="status" class="input"><option value="published" @selected(old('status', $event->status) === 'published')>Published</option><option value="draft" @selected(old('status', $event->status) === 'draft')>Draft (hidden)</option></select></div>
    <div class="row wrap"><button class="btn btn-p" type="submit">Save</button><a class="btn btn-t" href="{{ route('console.events.index') }}">Cancel</a></div>
</form>

@if($event->exists)
<form method="POST" action="{{ route('console.events.destroy', $event) }}">@csrf @method('DELETE')
    <button class="btn btn-d btn-sm" type="submit" data-confirm="Delete &quot;{{ $event->title }}&quot;?">Delete this event</button></form>
@endif
@endsection
