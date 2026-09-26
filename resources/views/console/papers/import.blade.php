@extends('layouts.app')

@section('title', 'Import questions')
@section('page_class', 'narrow')

@section('content')
<div class="col">
    <a class="link small" href="{{ route('console.papers.show', $paper) }}">&larr; {{ $paper->label() }}</a>
    <h1 class="h1">Import questions</h1>
    <p class="muted">Add many questions at once from a CSV file. They are added to the end of this paper.</p>
</div>

@if(session('import_errors'))
    <div class="alert err" role="alert">
        <p style="margin-bottom:8px"><b>Nothing was imported.</b> Fix these {{ session('import_error_total') > count(session('import_errors')) ? 'first ' . count(session('import_errors')) . ' ' : '' }}rows and upload the file again:</p>
        <ul style="margin:0;padding-left:20px">
            @foreach(session('import_errors') as $line => $message)<li>Line {{ $line }}: {{ $message }}</li>@endforeach
        </ul>
    </div>
@endif
@error('file')<div class="alert err">{{ $message }}</div>@enderror

<form method="POST" action="{{ route('console.import.store', $paper) }}" enctype="multipart/form-data" class="card stack" style="gap:16px">
    @csrf
    <div class="field">
        <label for="file">CSV file</label>
        <input id="file" type="file" name="file" accept=".csv,text/csv,text/plain" class="input" style="padding-top:10px" required>
        <span class="hint">Up to 1,000 rows and 2 MB. Save from Excel as "CSV UTF-8" so accents and symbols survive.</span>
    </div>
    <div class="row wrap"><button class="btn btn-p" type="submit">Import</button><a class="btn btn-o" href="{{ route('console.import.sample') }}">Download a sample file</a></div>
</form>

<section class="card stack" style="gap:12px">
    <h2 class="h2">The columns</h2>
    <div class="table-wrap"><table class="table">
        <thead><tr><th>Column</th><th>What goes in it</th></tr></thead>
        <tbody>
            <tr><td><b>question</b> *</td><td>The question text. For a passage, the reading text.</td></tr>
            <tr><td><b>option_a</b>, <b>option_b</b> *</td><td>At least two options are needed. <b>option_c</b>, <b>option_d</b>, <b>option_e</b> are optional.</td></tr>
            <tr><td><b>answer</b> *</td><td>The letter of the right option: A, B, C, D or E.</td></tr>
            <tr><td>type</td><td><code>question</code> (default) or <code>passage</code> for a reading text or instruction. Put a passage on the line before its questions.</td></tr>
            <tr><td>marks</td><td>A whole number from 1 to 20. Default 1.</td></tr>
            <tr><td>topic</td><td>Must match a topic name for {{ $paper->subject->name }} exactly. Add topics under Topics first.</td></tr>
            <tr><td>explanation_en, explanation_pcm</td><td>The explanation in English and in Pidgin. Optional.</td></tr>
        </tbody>
    </table></div>
    <p class="hint">Plain text is fine. For maths write \( x^2 + 1 \). A cell that starts with an HTML tag such as &lt;p&gt; is kept as HTML.</p>
</section>
@endsection
