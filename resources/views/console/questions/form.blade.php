@extends('layouts.app')

@section('title', $question->exists ? 'Edit question' : 'Add question')
@section('page_class', 'mid')

@php
    $isPassage = (bool) old('type') ? old('type') === 'passage' : (bool) $question->not_question;
    $editing = $question->exists;
    $action = $editing ? route('console.questions.update', $question) : route('console.questions.store', $paper);
    $letters = ['A', 'B', 'C', 'D', 'E'];
@endphp

@push('head')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css" rel="stylesheet">
@endpush

@section('content')
<div class="col">
    <a class="link small" href="{{ route('console.papers.show', $paper) }}">&larr; {{ $paper->label() }}</a>
    <h1 class="h1">{{ $editing ? 'Edit' : 'Add' }} {{ $isPassage ? 'passage or instruction' : 'question' }}</h1>
    @if($isPassage)<p class="muted">A reading text or instruction shown to students above the questions that follow it, until the next passage.</p>@endif
</div>

@if($errors->any())
    <div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ $action }}" class="stack" style="gap:22px">
    @csrf
    @if($editing) @method('PUT') @endif
    <input type="hidden" name="type" value="{{ $isPassage ? 'passage' : 'question' }}">

    <div class="field">
        <label for="question">{{ $isPassage ? 'Passage or instruction' : 'Question' }}</label>
        <textarea id="question" name="question" class="input rich" data-height="200" rows="6">{{ old('question', $question->question) }}</textarea>
        <span class="hint">You can add pictures and tables. For maths, write \( x^2 + 1 \).</span>
    </div>

    @unless($isPassage)
    <section class="stack" style="gap:12px">
        <h2 class="h3">Options</h2>
        @foreach($letters as $i => $letter)
            <div class="row" style="align-items:flex-start">
                <span class="bub" style="margin-top:8px">{{ $letter }}</span>
                <div class="grow field">
                    <input name="options[{{ $letter }}]" class="input" value="{{ old("options.$letter", $options[$letter] ?? '') }}" aria-label="Option {{ $letter }}" placeholder="{{ $i < 2 ? 'Required' : 'Optional' }}">
                </div>
            </div>
        @endforeach
        <div class="editor-grid">
            <div class="field">
                <label for="answer">Correct answer</label>
                <select id="answer" name="answer" class="input" required>
                    <option value="">Choose</option>
                    @foreach($letters as $letter)<option value="{{ $letter }}" @selected(old('answer', strtoupper(trim((string) $question->answer))) === $letter)>{{ $letter }}</option>@endforeach
                </select>
            </div>
            <div class="field">
                <label for="mark">Marks</label>
                <input id="mark" name="mark" type="number" min="1" max="20" class="input" value="{{ old('mark', $question->mark ?: 1) }}">
            </div>
        </div>
        <div class="field">
            <label for="topic_id">Topic <span class="muted">(optional)</span></label>
            <select id="topic_id" name="topic_id" class="input">
                <option value="">No topic</option>
                @foreach($topics as $t)<option value="{{ $t->id }}" @selected((string) old('topic_id', $question->topic_id) === (string) $t->id)>{{ $t->name }}</option>@endforeach
            </select>
            @if($topics->isEmpty())<span class="hint">There are no topics for {{ $paper->subject->name }} yet. <a class="link" href="{{ route('console.topics.index') }}">Add some</a> to help students find weak areas.</span>@endif
        </div>
    </section>

    <section class="stack" style="gap:16px">
        <h2 class="h3">Explanation <span class="muted small" style="font-weight:500">(shown after the student answers)</span></h2>
        <div class="field"><label for="explanation_en">In English</label><textarea id="explanation_en" name="explanation_en" class="input rich" data-height="110" data-mini="1">{{ old('explanation_en', $question->explanation_en) }}</textarea></div>
        <div class="field"><label for="explanation_pcm">In Pidgin</label><textarea id="explanation_pcm" name="explanation_pcm" class="input rich" data-height="110" data-mini="1">{{ old('explanation_pcm', $question->explanation_pcm) }}</textarea></div>
    </section>
    @endunless

    <div class="row wrap">
        <button class="btn btn-p" type="submit">{{ $editing ? 'Save changes' : 'Save' }}</button>
        @unless($editing)<button class="btn btn-o" type="submit" name="add_another" value="1">Save and add another</button>@endunless
        <a class="btn btn-t" href="{{ route('console.papers.show', $paper) }}">Cancel</a>
    </div>
</form>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script>
(function () {
  if (!window.jQuery || !jQuery.fn.summernote) { return; }   // the plain text boxes still work without the editor
  var csrf = document.querySelector('meta[name="csrf-token"]').content;
  var uploadUrl = @json(route('summernote.image.upload'));

  jQuery('textarea.rich').each(function () {
    var mini = this.dataset.mini === '1';
    jQuery(this).summernote({
      height: parseInt(this.dataset.height, 10) || 150,
      toolbar: mini
        ? [['style', ['bold', 'italic', 'superscript', 'subscript']], ['para', ['ul']], ['insert', ['picture']]]
        : [['style', ['bold', 'italic', 'underline', 'superscript', 'subscript']], ['para', ['ul', 'ol', 'paragraph']], ['insert', ['table', 'picture']], ['view', ['undo', 'redo', 'codeview']]],
      callbacks: {
        onImageUpload: function (files) {
          var $editor = jQuery(this);
          var fd = new FormData();
          fd.append('image', files[0]);
          fd.append('_token', csrf);
          jQuery.ajax({ url: uploadUrl, method: 'POST', data: fd, processData: false, contentType: false,
            success: function (url) { $editor.summernote('insertImage', url); },
            error: function () { window.alert('The picture could not be uploaded. Try a smaller file.'); } });
        }
      }
    });
  });
})();
</script>
@endpush
