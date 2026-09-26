@extends('layouts.app')

@section('title', $post->exists ? 'Edit article' : 'New article')
@section('page_class', 'mid')

@php
    $editing = $post->exists;
    $category = old('category', $post->category);
    $publishedAt = old('published_at', $post->published_at?->format('Y-m-d\TH:i'));
@endphp

@push('head')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
@endpush

@section('content')
<div class="col">
    <a class="link small" href="{{ route('console.posts.index') }}">&larr; Content</a>
    <h1 class="h1">{{ $editing ? 'Edit article' : 'New article' }}</h1>
</div>

@if($errors->any())<div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('console.posts.update', $post) : route('console.posts.store') }}" class="stack" style="gap:20px">
    @csrf @if($editing) @method('PUT') @endif

    <div class="grid2">
        <div class="field"><label for="category">Category</label>
            <select id="category" name="category" class="input">@foreach(\App\Models\Post::categories() as $k => $c)<option value="{{ $k }}" @selected($category === $k)>{{ $c['label'] }}</option>@endforeach</select></div>
        <div class="field"><label for="exam_id">About an exam <span class="muted">(optional)</span></label>
            <select id="exam_id" name="exam_id" class="input"><option value="">None</option>@foreach($exams as $e)<option value="{{ $e->id }}" @selected((string) old('exam_id', $post->exam_id) === (string) $e->id)>{{ $e->name }}</option>@endforeach</select></div>
    </div>

    <div class="field"><label for="title">Title</label><input id="title" name="title" class="input @error('title') err @enderror" value="{{ old('title', $post->title) }}" maxlength="160" required></div>
    <div class="field"><label for="excerpt">Short summary <span class="muted">(optional, shown on cards)</span></label>
        <textarea id="excerpt" name="excerpt" class="input" rows="2" maxlength="300" style="padding:12px 14px">{{ old('excerpt', $post->excerpt) }}</textarea></div>

    <div class="field"><label for="body">Article</label>
        <textarea id="body" name="body" class="input rich" rows="10">{{ old('body', $post->body) }}</textarea>
        <span class="hint">You can add pictures, links, lists and tables.</span></div>

    <section class="card stack" id="scholarship-fields" style="gap:14px">
        <h2 class="h3">Scholarship details</h2>
        <div class="grid2">
            <div class="field"><label for="source">Offered by</label><input id="source" name="source" class="input" value="{{ old('source', $post->source) }}" maxlength="160" placeholder="e.g. Federal Government"></div>
            <div class="field"><label for="deadline">Deadline</label><input id="deadline" name="deadline" type="date" class="input" value="{{ old('deadline', $post->deadline?->format('Y-m-d')) }}"></div>
        </div>
    </section>

    <div class="field"><label for="link_url">Link <span class="muted">(optional: where to apply or read more)</span></label>
        <input id="link_url" name="link_url" type="url" class="input @error('link_url') err @enderror" value="{{ old('link_url', $post->link_url) }}" maxlength="500" placeholder="https://"></div>

    <div class="field"><label for="cover">Cover picture <span class="muted">(optional, JPG, PNG or WebP up to 2 MB)</span></label>
        @if($post->cover_path)
            <div class="row"><img src="{{ $post->coverUrl() }}" alt="" style="height:64px;border-radius:10px"><label class="row small"><input type="checkbox" name="remove_cover" value="1"> Remove picture</label></div>
        @endif
        <input id="cover" name="cover" type="file" class="input" accept="image/jpeg,image/png,image/webp" style="padding:10px 14px"></div>

    <section class="card stack" style="gap:14px">
        <h2 class="h3">Publishing</h2>
        <div class="grid2">
            <div class="field"><label for="status">Status</label>
                <select id="status" name="status" class="input"><option value="draft" @selected(old('status', $post->status) === 'draft')>Draft (hidden)</option><option value="published" @selected(old('status', $post->status) === 'published')>Published</option></select></div>
            <div class="field"><label for="published_at">Publish date <span class="muted">(empty means now; a future date schedules it)</span></label>
                <input id="published_at" name="published_at" type="datetime-local" class="input" value="{{ $publishedAt }}"></div>
        </div>
        <label class="row small" style="gap:8px"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $post->is_featured))> Show first on the news list and landing page</label>
    </section>

    <div class="row wrap">
        <button class="btn btn-p" type="submit">{{ $editing ? 'Save changes' : 'Save' }}</button>
        <a class="btn btn-t" href="{{ route('console.posts.index') }}">Cancel</a>
        @if($editing)<a class="btn btn-o" href="{{ route('articles.show', $post->slug) }}" target="_blank" rel="noopener">Preview</a>@endif
    </div>
</form>

@if($editing)
<form method="POST" action="{{ route('console.posts.destroy', $post) }}">@csrf @method('DELETE')
    <button class="btn btn-d btn-sm" type="submit" data-confirm="Delete &quot;{{ $post->title }}&quot; for good?">Delete this article</button></form>
@endif
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script>
(function () {
  var cat = document.getElementById('category'), box = document.getElementById('scholarship-fields');
  function sync() { box.style.display = cat.value === 'scholarship' ? '' : 'none'; }
  cat.addEventListener('change', sync); sync();

  if (!window.jQuery || !jQuery.fn.summernote) { return; }   // the plain text box still works without the editor
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var uploadUrl = @json(route('summernote.image.upload'));
  jQuery('textarea.rich').summernote({
    height: 320,
    toolbar: [['style', ['style', 'bold', 'italic', 'underline']], ['para', ['ul', 'ol', 'paragraph']], ['insert', ['link', 'table', 'picture']], ['view', ['undo', 'redo', 'codeview']]],
    callbacks: {
      onImageUpload: function (files) {
        var $editor = jQuery(this), fd = new FormData();
        fd.append('image', files[0]); fd.append('_token', csrf);
        jQuery.ajax({ url: uploadUrl, method: 'POST', data: fd, processData: false, contentType: false,
          success: function (url) { $editor.summernote('insertImage', url); },
          error: function () { window.alert('The picture could not be uploaded. Try a smaller file.'); } });
      }
    }
  });
})();
</script>
@endpush
