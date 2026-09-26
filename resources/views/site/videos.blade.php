@extends('layouts.public')

@section('title', 'Videos')
@section('meta_description', 'Watch lessons, exam tips and updates from TestaCBT on YouTube, Facebook, X and TikTok.')

@push('head')
    {!! \App\Support\SiteInfo::jsonLd([
        '@context' => 'https://schema.org',
        '@graph' => $videos->filter(fn ($v) => $v->platform === 'youtube' && $v->thumbnail_url)->map(fn ($v) => array_filter([
            '@type' => 'VideoObject',
            'name' => $v->title,
            'description' => $v->description ?: $v->title,
            'thumbnailUrl' => [$v->thumbnail_url],
            'uploadDate' => $v->created_at->toAtomString(),
            'embedUrl' => $v->embed_url,
            'contentUrl' => $v->url,
        ]))->values()->all(),
    ]) !!}
@endpush

@section('content')
<section class="section" style="padding-top:12px">
    <div class="section-head"><div><h1 class="h1">Videos</h1><p>Lessons, exam tips and updates. Press play to watch here, or open the video on its own site.</p></div></div>

    @if(count($platforms) > 1 || $exams->isNotEmpty())
        <div class="chips scroll" style="margin-bottom:18px">
            <a class="chip {{ ! $platform && ! $exam ? 'on' : '' }}" href="{{ route('videos') }}">All</a>
            @foreach($platforms as $p)<a class="chip {{ $platform === $p ? 'on' : '' }}" href="{{ route('videos', ['platform' => $p]) }}">{{ \App\Services\VideoLink::LABELS[$p] }}</a>@endforeach
            @foreach($exams as $e)<a class="chip {{ $exam && $exam->id === $e->id ? 'on' : '' }}" href="{{ route('videos', ['exam' => $e->slug]) }}">{{ $e->name }}</a>@endforeach
        </div>
    @endif

    @if($videos->isEmpty())
        <div class="card empty"><span class="sq c-p"><x-icon name="play"/></span><p class="h3">No videos yet</p><p class="small muted">New videos are added here as they are published.</p></div>
    @else
        <div class="post-grid">@foreach($videos as $video)@include('site.videos._card')@endforeach</div>
        <div style="margin-top:24px">{{ $videos->links('vendor.pagination.testacbt') }}</div>
    @endif
</section>
@endsection
