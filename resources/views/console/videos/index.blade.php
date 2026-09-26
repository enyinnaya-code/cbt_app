@extends('layouts.app')

@section('title', 'Videos')

@section('content')
<div class="row between wrap">
    <div class="col"><h1 class="h1">Content</h1><p class="muted">Videos from YouTube, Facebook, X and TikTok, shown on the website.</p></div>
    <a class="btn btn-p" href="{{ route('console.videos.create') }}"><x-icon name="plus" size="s"/>Add a video</a>
</div>
@include('console._content-tabs')

<div class="table-wrap">
    <table class="table">
        <thead><tr><th style="width:56px">S/N</th><th>Title</th><th>Site</th><th>Exam</th><th>Status</th><th>Added</th><th></th></tr></thead>
        <tbody>
        @forelse($videos as $v)
            <tr>
                <td class="muted">{{ $videos->firstItem() + $loop->index }}</td>
                <td><a class="link" href="{{ route('console.videos.edit', $v) }}">{{ $v->title }}</a>@if($v->is_featured)<span class="badge" style="margin-left:6px">Featured</span>@endif</td>
                <td><span class="badge {{ $v->badgeClass() }}">{{ $v->platformLabel() }}</span></td>
                <td class="small">{{ $v->exam?->name ?? 'Any' }}</td>
                <td><span class="badge {{ $v->status === 'published' ? 'g' : 'neutral' }}">{{ $v->status === 'published' ? 'Live' : 'Draft' }}</span></td>
                <td class="small muted">{{ $v->created_at->format('j M Y') }}</td>
                <td><div class="actions"><a class="btn btn-o btn-sm" href="{{ $v->url }}" target="_blank" rel="noopener noreferrer">Open</a><a class="btn btn-o btn-sm" href="{{ route('console.videos.edit', $v) }}">Edit</a></div></td>
            </tr>
        @empty
            <tr><td colspan="7"><div class="empty"><p class="h3">No videos yet</p><p class="small muted">Paste a link to a video and it appears on the website.</p></div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $videos->links('vendor.pagination.testacbt') }}
@endsection
