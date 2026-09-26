@extends('layouts.app')

@section('title', 'New paper')
@section('page_class', 'narrow')

@section('content')
<div class="col">
    <a class="link small" href="{{ route('console.papers.index') }}">&larr; Papers</a>
    <h1 class="h1">New paper</h1>
    <p class="muted">It starts as a draft, so students cannot see it until an admin publishes it.</p>
</div>

<form method="POST" action="{{ route('console.papers.store') }}" class="card stack" style="gap:18px">
    @csrf
    @include('console.papers._fields')
    <div class="row"><button class="btn btn-p" type="submit">Create paper</button><a class="btn btn-t" href="{{ route('console.papers.index') }}">Cancel</a></div>
</form>
@endsection
