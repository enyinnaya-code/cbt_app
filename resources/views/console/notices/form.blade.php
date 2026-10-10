@extends('layouts.app')

@section('title', $notice->exists ? 'Edit announcement' : 'New announcement')
@section('page_class', 'narrow')

@section('content')
<div class="col">
    <a class="link small" href="{{ route('console.notices.index') }}">&larr; Announcements</a>
    <h1 class="h1">{{ $notice->exists ? 'Edit announcement' : 'New announcement' }}</h1>
</div>

@if($errors->any())<div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ $notice->exists ? route('console.notices.update', $notice) : route('console.notices.store') }}" class="card stack" style="gap:16px">
    @csrf @if($notice->exists) @method('PUT') @endif
    <div class="field"><label for="title">Headline</label><input id="title" name="title" class="input" value="{{ old('title', $notice->title) }}" maxlength="120" required placeholder="e.g. WAEC 2027 timetable is out"></div>
    <div class="field"><label for="body">Message <span class="muted">(optional)</span></label><textarea id="body" name="body" class="input" rows="4" maxlength="600" style="padding:12px 14px">{{ old('body', $notice->body) }}</textarea></div>
    <div class="grid2">
        <div class="field"><label for="tone">Look</label><select id="tone" name="tone" class="input">@foreach(\App\Models\Notice::tones() as $k => $label)<option value="{{ $k }}" @selected(old('tone', $notice->tone) === $k)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label for="audience">Shown on</label><select id="audience" name="audience" class="input">@foreach(\App\Models\Notice::audiences() as $k => $label)<option value="{{ $k }}" @selected(old('audience', $notice->audience) === $k)>{{ $label }}</option>@endforeach</select></div>
    </div>
    <div class="grid2">
        <div class="field"><label for="link_url">Link <span class="muted">(optional)</span></label><input id="link_url" name="link_url" type="url" class="input" value="{{ old('link_url', $notice->link_url) }}" maxlength="500" placeholder="https://"></div>
        <div class="field"><label for="link_label">Button words <span class="muted">(optional)</span></label><input id="link_label" name="link_label" class="input" value="{{ old('link_label', $notice->link_label) }}" maxlength="40" placeholder="Read more"></div>
    </div>
    <div class="grid2">
        <div class="field"><label for="starts_at">Show from <span class="muted">(optional)</span></label><input id="starts_at" name="starts_at" type="datetime-local" class="input" value="{{ old('starts_at', $notice->starts_at?->format('Y-m-d\TH:i')) }}"></div>
        <div class="field"><label for="ends_at">Hide after <span class="muted">(optional)</span></label><input id="ends_at" name="ends_at" type="datetime-local" class="input" value="{{ old('ends_at', $notice->ends_at?->format('Y-m-d\TH:i')) }}"></div>
    </div>
    <label class="row" style="gap:10px"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $notice->is_active))> <span><b>Active</b> <span class="muted small">students can see it now (inside the dates above)</span></span></label>
    <div class="row wrap"><button class="btn btn-p" type="submit">Save</button><a class="btn btn-t" href="{{ route('console.notices.index') }}">Cancel</a></div>
</form>

@if($notice->exists)
<form method="POST" action="{{ route('console.notices.destroy', $notice) }}">@csrf @method('DELETE')
    <button class="btn btn-d btn-sm" type="submit" data-confirm="Delete &quot;{{ $notice->title }}&quot;?" data-confirm-tone="danger">Delete this announcement</button></form>
@endif
@endsection
