{{-- One video. Expects $video. The player is only loaded when the visitor presses play, so the page stays fast and no
     other site is contacted until then. --}}
<article class="video-card">
    <div class="video-frame {{ $video->isTall() ? 'tall' : ($video->platform === 'x' ? 'post' : '') }}" data-embed="{{ $video->embed_url }}" data-title="{{ $video->title }}">
        <button type="button" class="video-play" aria-label="Play video: {{ $video->title }}">
            @if($video->thumbnail_url)<img src="{{ $video->thumbnail_url }}" alt="" loading="lazy" width="480" height="360">@endif
            <span class="video-badge"><x-icon name="play"/></span>
            @unless($video->thumbnail_url)<span class="video-name">{{ $video->platformLabel() }}</span>@endunless
        </button>
    </div>
    <div class="pc-body">
        <div class="row" style="gap:6px;flex-wrap:wrap"><span class="badge {{ $video->badgeClass() }}">{{ $video->platformLabel() }}</span>@if($video->exam)<span class="badge neutral">{{ $video->exam->name }}</span>@endif</div>
        <h3>{{ $video->title }}</h3>
        @if($video->description)<p class="sum">{{ $video->description }}</p>@endif
        <a class="link small" href="{{ $video->url }}" target="_blank" rel="noopener noreferrer">Watch on {{ $video->platformLabel() }}</a>
    </div>
</article>
