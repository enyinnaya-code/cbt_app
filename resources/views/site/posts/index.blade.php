@extends('layouts.public')

@section('title', $heading)

@section('content')
<section class="section" style="padding-top:12px">
    <div class="section-head">
        <div><h1 class="h1">{{ $heading }}</h1><p>{{ $intro }}</p></div>
        <form method="GET" class="row" style="gap:8px">
            @if($chosen)<input type="hidden" name="category" value="{{ $chosen }}">@endif
            <div class="input-wrap"><x-icon name="search" size="s"/><input class="input" type="search" name="q" value="{{ $search }}" placeholder="Search" aria-label="Search" style="min-height:42px;border-radius:12px;min-width:200px"></div>
        </form>
    </div>

    @if(count($group) > 1)
        <div class="chips scroll" style="margin-bottom:18px">
            <a class="chip {{ $chosen ? '' : 'on' }}" href="{{ route($page) }}">All</a>
            @foreach($group as $key)
                <a class="chip {{ $chosen === $key ? 'on' : '' }}" href="{{ route($page, ['category' => $key]) }}">{{ \App\Models\Post::categories()[$key]['label'] }}</a>
            @endforeach
        </div>
    @endif

    @if($posts->isEmpty())
        <div class="card empty"><span class="sq c-g"><x-icon name="news"/></span><p class="h3">{{ $search ? 'Nothing matches that search' : 'Nothing here yet' }}</p><p class="small muted">{{ $search ? 'Try a different word.' : 'Check back soon.' }}</p></div>
    @else
        <div class="post-grid">@foreach($posts as $post)@include('site.posts._card')@endforeach</div>
        <div style="margin-top:24px">{{ $posts->links('vendor.pagination.testacbt') }}</div>
    @endif
</section>
@endsection
