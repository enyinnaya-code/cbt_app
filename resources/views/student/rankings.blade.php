@extends('layouts.app')

@section('title', 'Rankings')
@section('page_class', 'mid')

@php $tabs = ['week' => 'This week', 'all' => 'All time', 'schools' => 'Schools']; @endphp

@section('content')
<div class="row between wrap">
    <div class="col"><h1 class="h1">Rankings</h1><p class="muted">From the daily challenge. Students are shown by nickname, never by real name.</p></div>
    @if($joined)<a class="btn btn-o btn-sm" href="{{ route('rankings.join') }}">Edit my profile</a>@endif
</div>

@unless($joined)
    <div class="card stack" style="gap:8px">
        <p class="h3">Want to be on the board?</p>
        <p class="small muted">Join the daily challenge with a nickname and a picture. It is optional, and you can leave whenever you like.</p>
        <a class="btn btn-p btn-sm" style="align-self:flex-start" href="{{ route('rankings.join') }}">Join the daily challenge</a>
    </div>
@endunless

<div class="seg" role="tablist" aria-label="Board">
    @foreach($tabs as $key => $label)<a href="{{ route('rankings', ['scope' => $key]) }}" class="{{ $scope === $key ? 'on' : '' }}" role="tab">{{ $label }}</a>@endforeach
</div>

@if($scope !== 'schools' && $board['me'])
    <div class="card primary">
        <p class="small" style="opacity:.85">{{ $board['period'] }}</p>
        <p class="display" style="font-size:36px;font-weight:800;line-height:1.1">
            @if($board['me']['rank']) #{{ $board['me']['rank'] }} @else No rank yet @endif
            <span style="font-size:16px;font-weight:600">&middot; {{ number_format($board['me']['points']) }} points</span>
        </p>
        @if(! $board['me']['played'])<p class="small" style="opacity:.9">Finish today's challenge to get on the board.</p>@endif
    </div>
@endif

<section class="card">
    @forelse($board['rows'] as $r)
        <div class="li" @if(! empty($r['me']) || ! empty($r['mine'])) style="background:var(--primary-soft);border-radius:12px;padding-left:10px;padding-right:10px" @endif>
            <div class="sq {{ $r['rank'] <= 3 ? 'c-a' : '' }}" style="width:38px">{{ $r['rank'] }}</div>
            @if($scope === 'schools')
                <div class="grow"><p class="h3" style="overflow-wrap:anywhere">{{ $r['school'] }}</p><p class="small muted">{{ $r['members'] }} students played</p></div>
                <div class="h3">{{ number_format($r['points']) }} <span class="small muted" style="font-weight:500">avg</span></div>
            @else
                <span style="font-size:24px" aria-hidden="true">{{ $r['emoji'] }}</span>
                <div class="grow" style="min-width:0"><p class="h3" style="overflow-wrap:anywhere">{{ $r['nickname'] }}@if(! empty($r['me'])) <span class="badge g">you</span>@endif</p>
                    @if($r['school'])<p class="small muted" style="overflow-wrap:anywhere">{{ $r['school'] }}</p>@endif</div>
                <div class="h3">{{ number_format($r['points']) }} <span class="small muted" style="font-weight:500">pts</span></div>
            @endif
        </div>
    @empty
        <div class="empty">
            <span class="sq c-a"><x-icon name="trophy"/></span>
            <p class="h3">{{ $scope === 'schools' ? 'No school has enough players yet' : 'No one is on the board yet' }}</p>
            <p class="small muted">{{ $scope === 'schools' ? 'A school appears once ' . ($board['min_members'] ?? 3) . ' students from it have finished a challenge this week.' : 'Finish a daily challenge to be the first.' }}</p>
        </div>
    @endforelse
</section>

<p class="small muted">Points: 10 for every right answer plus 100 for finishing. <a class="link" href="{{ route('privacy') }}">Privacy policy</a></p>
@endsection
