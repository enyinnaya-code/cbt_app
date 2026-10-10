@extends('layouts.app')

@section('title', 'Announcements')
@section('page_class', 'mid')

@section('content')
<div class="row between wrap">
    <div class="col"><h1 class="h1">Content</h1><p class="muted">Announcements show as a card at the top of Home, on the website and in the app. Students can close them.</p></div>
    <a class="btn btn-p" href="{{ route('console.notices.create') }}"><x-icon name="plus" size="s"/>New announcement</a>
</div>
@include('console._content-tabs')

<section class="card">
    @forelse($notices as $n)
        <div class="li" style="flex-wrap:wrap">
            <div class="grow">
                <p class="h3">{{ $n->title }}
                    @if($n->isShowing())<span class="badge green">Live</span>
                    @elseif($n->is_active)<span class="badge neutral">{{ $n->ends_at && $n->ends_at->isPast() ? 'Ended' : 'Scheduled' }}</span>
                    @else<span class="badge neutral">Off</span>@endif
                </p>
                <p class="small muted">{{ \App\Models\Notice::audiences()[$n->audience] ?? $n->audience }}
                    @if($n->starts_at) &middot; from {{ $n->starts_at->format('j M Y, g:i a') }}@endif
                    @if($n->ends_at) &middot; until {{ $n->ends_at->format('j M Y, g:i a') }}@endif</p>
            </div>
            <form method="POST" action="{{ route('console.notices.toggle', $n) }}">@csrf @method('PATCH')
                <button class="btn btn-o btn-sm" type="submit" @if($n->is_active) data-confirm="Switch off &quot;{{ $n->title }}&quot;? Students will stop seeing it." data-confirm-label="Switch off" @endif>{{ $n->is_active ? 'Switch off' : 'Switch on' }}</button>
            </form>
            <a class="btn btn-o btn-sm" href="{{ route('console.notices.edit', $n) }}">Edit</a>
        </div>
    @empty
        <div class="empty"><p class="small muted">No announcements yet. Write one and it appears for students as soon as it is on.</p></div>
    @endforelse
</section>
@endsection
