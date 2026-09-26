{{-- One article card. Expects $post. --}}
<a class="post-card" href="{{ route('articles.show', $post->slug) }}">
    <div class="cover">
        @if($post->coverUrl())<img src="{{ $post->coverUrl() }}" alt="" loading="lazy">
        @else<span class="sq {{ $post->tileClass() }}"><x-icon name="{{ $post->tileIcon() }}"/></span>@endif
    </div>
    <div class="pc-body">
        <div class="row" style="gap:6px;flex-wrap:wrap">
            <span class="badge {{ $post->badgeClass() }}">{{ $post->categoryLabel() }}</span>
            @if($post->category === 'scholarship' && $post->deadline)
                <span class="badge {{ $post->isClosed() ? 'neutral' : 'r' }}">{{ $post->isClosed() ? 'Closed' : 'Closes ' . $post->deadline->format('j M Y') }}</span>
            @endif
        </div>
        <h3>{{ $post->title }}</h3>
        <p class="sum">{{ $post->summary() }}</p>
        <div class="meta"><span>{{ $post->published_at?->format('j M Y') }}</span><span>&middot; {{ $post->readMinutes() }} min read</span></div>
    </div>
</a>
