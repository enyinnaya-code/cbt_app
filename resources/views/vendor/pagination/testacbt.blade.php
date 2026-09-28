@if ($paginator->hasPages())
@php
    // Previous, the first page, a window of pages around the current one, the last page, Next.
    // Long lists stay short: 1 ... 6 7 [8] 9 10 ... 40
    $current = $paginator->currentPage();
    $last = $paginator->lastPage();
    $from = max(2, $current - 2);
    $to = min($last - 1, $current + 2);
@endphp
<nav class="pager" role="navigation" aria-label="Pages">
    @if($current > 1)
        <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">Prev</a>
    @else
        <span class="off">Prev</span>
    @endif

    @if($current === 1)<span class="cur" aria-current="page">1</span>@else<a href="{{ $paginator->url(1) }}" aria-label="First page">1</a>@endif
    @if($from > 2)<span class="off">&hellip;</span>@endif
    @for($p = $from; $p <= $to; $p++)
        @if($p === $current)<span class="cur" aria-current="page">{{ $p }}</span>
        @else<a href="{{ $paginator->url($p) }}">{{ $p }}</a>@endif
    @endfor
    @if($to < $last - 1)<span class="off">&hellip;</span>@endif
    @if($current === $last)<span class="cur" aria-current="page">{{ $last }}</span>@else<a href="{{ $paginator->url($last) }}" aria-label="Last page">{{ $last }}</a>@endif

    @if($current < $last)
        <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">Next</a>
    @else
        <span class="off">Next</span>
    @endif
</nav>
@endif
