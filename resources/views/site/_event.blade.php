{{-- One event row. Expects $e. --}}
<div class="ev {{ $e->isOver() ? 'over' : '' }}">
    <div class="date-tile"><b>{{ $e->starts_on->format('j') }}</b><span>{{ $e->starts_on->format('M') }}</span></div>
    <div class="grow stack" style="gap:3px">
        <div class="row" style="gap:6px;flex-wrap:wrap"><span class="h3">{{ $e->title }}</span><span class="badge neutral">{{ $e->kindLabel() }}</span>@if($e->exam)<span class="badge g">{{ $e->exam->name }}</span>@endif</div>
        <p class="small muted">{{ $e->dateLabel() }}@if($e->location) &middot; {{ $e->location }}@endif</p>
        @if($e->description)<p class="small">{{ $e->description }}</p>@endif
    </div>
    @if($e->link_url)<a class="link small" href="{{ $e->link_url }}" target="_blank" rel="noopener noreferrer">Details</a>@endif
</div>
