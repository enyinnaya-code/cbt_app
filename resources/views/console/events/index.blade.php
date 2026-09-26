@extends('layouts.app')

@section('title', 'Events')
@section('page_class', 'mid')

@section('content')
<div class="row between wrap">
    <div class="col"><h1 class="h1">Content</h1><p class="muted">Upcoming exam dates, deadlines and webinars shown on the landing page.</p></div>
    <a class="btn btn-p" href="{{ route('console.events.create') }}"><x-icon name="plus" size="s"/>New event</a>
</div>
@include('console._content-tabs')

@foreach([['Upcoming', $upcoming, 'No upcoming events. Add the next exam date or deadline.'], ['Recently passed', $past, null]] as [$title, $rows, $emptyText])
    @if($rows->isNotEmpty() || $emptyText)
    <section class="card">
        <h2 class="h2" style="margin-bottom:4px">{{ $title }}</h2>
        @forelse($rows as $e)
            <div class="li" style="flex-wrap:wrap">
                <div class="date-tile" style="width:52px"><b>{{ $e->starts_on->format('j') }}</b><span>{{ $e->starts_on->format('M') }}</span></div>
                <div class="grow"><p class="h3">{{ $e->title }} @if($e->status === 'draft')<span class="badge neutral">Draft</span>@endif</p>
                    <p class="small muted">{{ $e->kindLabel() }} &middot; {{ $e->dateLabel() }}@if($e->exam) &middot; {{ $e->exam->name }}@endif</p></div>
                <a class="btn btn-o btn-sm" href="{{ route('console.events.edit', $e) }}">Edit</a>
            </div>
        @empty
            <div class="empty"><p class="small muted">{{ $emptyText }}</p></div>
        @endforelse
    </section>
    @endif
@endforeach
@endsection
