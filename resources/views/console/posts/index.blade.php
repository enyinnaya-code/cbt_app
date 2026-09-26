@extends('layouts.app')

@section('title', 'Articles')

@section('content')
<div class="row between wrap">
    <div class="col"><h1 class="h1">Content</h1><p class="muted">News, exam updates, result releases, scholarships and blog articles for the public site.</p></div>
    <a class="btn btn-p" href="{{ route('console.posts.create') }}"><x-icon name="plus" size="s"/>New article</a>
</div>
@include('console._content-tabs')

<form method="GET" class="filters card" style="padding:14px">
    <div class="field"><label for="f-cat">Category</label>
        <select id="f-cat" name="category" class="input"><option value="">All</option>@foreach(\App\Models\Post::categories() as $k => $c)<option value="{{ $k }}" @selected(($filters['category'] ?? null) === $k)>{{ $c['label'] }}</option>@endforeach</select></div>
    <div class="field"><label for="f-status">Status</label>
        <select id="f-status" name="status" class="input"><option value="">All</option><option value="draft" @selected(($filters['status'] ?? null) === 'draft')>Draft</option><option value="published" @selected(($filters['status'] ?? null) === 'published')>Published</option></select></div>
    <div class="field"><label for="f-q">Search title</label><input id="f-q" name="q" class="input" value="{{ $filters['q'] ?? '' }}"></div>
    <div class="row"><button class="btn btn-o btn-sm" type="submit">Filter</button>@if(array_filter($filters))<a class="btn btn-t btn-sm" href="{{ route('console.posts.index') }}">Clear</a>@endif</div>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
        @forelse($posts as $p)
            <tr>
                <td><a class="link" href="{{ route('console.posts.edit', $p) }}">{{ $p->title }}</a>@if($p->is_featured)<span class="badge" style="margin-left:6px">Featured</span>@endif</td>
                <td><span class="badge {{ $p->badgeClass() }}">{{ $p->categoryLabel() }}</span></td>
                <td>
                    @if($p->status === 'draft')<span class="badge neutral">Draft</span>
                    @elseif($p->published_at && $p->published_at->isFuture())<span class="badge">Scheduled</span>
                    @else<span class="badge g">Live</span>@endif
                </td>
                <td class="small muted">{{ $p->published_at?->format('j M Y') ?? $p->created_at->format('j M Y') }}</td>
                <td><div class="actions">
                    <a class="btn btn-o btn-sm" href="{{ route('articles.show', $p->slug) }}" target="_blank" rel="noopener">View</a>
                    <a class="btn btn-o btn-sm" href="{{ route('console.posts.edit', $p) }}">Edit</a>
                </div></td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty"><p class="h3">No articles yet</p><p class="small muted">Write your first one. It stays a draft until you publish it.</p></div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $posts->links('vendor.pagination.testacbt') }}
@endsection
