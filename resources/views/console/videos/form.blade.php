@extends('layouts.app')

@section('title', $video->exists ? 'Edit video' : 'Add a video')
@section('page_class', 'narrow')

@section('content')
<div class="col">
    <a class="link small" href="{{ route('console.videos.index') }}">&larr; Videos</a>
    <h1 class="h1">{{ $video->exists ? 'Edit video' : 'Add a video' }}</h1>
</div>

@if($errors->any())<div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ $video->exists ? route('console.videos.update', $video) : route('console.videos.store') }}" class="card stack" style="gap:16px">
    @csrf @if($video->exists) @method('PUT') @endif
    <div class="field"><label for="url">Video link</label>
        <input id="url" name="url" type="url" class="input @error('url') err @enderror" value="{{ old('url', $video->url) }}" maxlength="500" required placeholder="https://www.youtube.com/watch?v=...">
        <span class="hint">Works with YouTube (videos, Shorts and playlists), Facebook videos and reels, X posts with video, and TikTok videos. Copy the address from the browser or the Share button.</span></div>
    <div class="field"><label for="title">Title</label><input id="title" name="title" class="input" value="{{ old('title', $video->title) }}" maxlength="160" required></div>
    <div class="field"><label for="description">Short description <span class="muted">(optional)</span></label><textarea id="description" name="description" class="input" rows="3" maxlength="400" style="padding:12px 14px">{{ old('description', $video->description) }}</textarea></div>
    <div class="grid2">
        <div class="field"><label for="exam_id">About an exam <span class="muted">(optional)</span></label>
            <select id="exam_id" name="exam_id" class="input"><option value="">Any</option>@foreach($exams as $e)<option value="{{ $e->id }}" @selected((string) old('exam_id', $video->exam_id) === (string) $e->id)>{{ $e->name }}</option>@endforeach</select></div>
        <div class="field"><label for="status">Status</label>
            <select id="status" name="status" class="input"><option value="published" @selected(old('status', $video->status) === 'published')>Published</option><option value="draft" @selected(old('status', $video->status) === 'draft')>Draft (hidden)</option></select></div>
    </div>
    <label class="row small" style="gap:8px"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $video->is_featured))> Show first on the videos page and the home page</label>
    <div class="row wrap"><button class="btn btn-p" type="submit">Save</button><a class="btn btn-t" href="{{ route('console.videos.index') }}">Cancel</a></div>
</form>

@if($video->exists)
<form method="POST" action="{{ route('console.videos.destroy', $video) }}">@csrf @method('DELETE')
    <button class="btn btn-d btn-sm" type="submit" data-confirm="Delete &quot;{{ $video->title }}&quot; from the website?">Delete this video</button></form>
@endif
@endsection
