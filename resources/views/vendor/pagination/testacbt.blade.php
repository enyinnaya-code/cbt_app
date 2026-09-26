@if ($paginator->hasPages())
@php
    // First, Previous, a window of pages around the current one, Next, Last.
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $from = max(1, $current - 2);
    $to = min($last, $current + 2);
@endphp
<nav class="pager" role="navigation" aria-label="Pages">
    @if($current > 1)
        <a href="{{ $paginator->url(1) }}" aria-label="First page">First</a>
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">Prev</a>
    @else
        <span class="off">First</span><span class="off">Prev</span>
    @endif

    @if($from > 1)<span class="off">&hellip;</span>@endif
    @for($p = $from; $p <= $to; $p++)
        @if($p === $current)<span class="cur" aria-current="page">{{ $p }}</span>
        @else<a href="{{ $paginator->url($p) }}">{{ $p }}</a>@endif
    @endfor
    @if($to < $last)<span class="off">&hellip;</span>@endif

    @if($current < $last)
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">Next</a>
        <a href="{{ $paginator->url($last) }}" aria-label="Last page">Last</a>
    @else
        <span class="off">Next</span><span class="off">Last</span>
    @endif
</nav>
@endif
