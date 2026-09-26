@extends('layouts.app')

@section('title', 'Offline packs')
@section('page_class', 'mid')

@php $isAdmin = auth()->user()->isAdmin(); @endphp

@section('content')
<div class="row between wrap">
    <div class="col"><h1 class="h1">Offline packs</h1><p class="muted">The compressed files the mobile app downloads. Publishing a paper updates its pack automatically.</p></div>
    @if($isAdmin && $rows->isNotEmpty())
        <form method="POST" action="{{ route('console.packs.rebuild') }}">@csrf<button class="btn btn-o" type="submit"><x-icon name="refresh" size="s"/>Rebuild all</button></form>
    @endif
</div>

@if(session('pack_warnings'))
    <div class="alert info"><p style="margin-bottom:6px"><b>Worth a look</b></p><ul style="margin:0;padding-left:18px">@foreach(session('pack_warnings') as $w)<li>{{ $w }}</li>@endforeach</ul></div>
@endif

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Exam</th><th>Subject</th><th>Version</th><th>Papers</th><th>Questions</th><th>Size</th><th>Built</th><th></th></tr></thead>
        <tbody>
        @forelse($rows as $r)
            <tr>
                <td>{{ $r->exam }}</td><td>{{ $r->subject }}</td>
                @if($r->pack)
                    <td>v{{ $r->pack->version }} @if($r->stale)<span class="badge">out of date</span>@endif</td>
                    <td>{{ $r->pack->paper_count }}</td><td>{{ number_format($r->pack->question_count) }}</td>
                    <td>{{ $r->pack->size_bytes >= 1048576 ? round($r->pack->size_bytes / 1048576, 1) . ' MB' : round($r->pack->size_bytes / 1024, 1) . ' KB' }}</td>
                    <td class="small muted">{{ $r->pack->built_at->format('j M Y g:i a') }}</td>
                @else
                    <td colspan="5"><span class="badge">not built yet</span></td>
                @endif
                <td><div class="actions">@if($isAdmin)
                    <form method="POST" action="{{ route('console.packs.rebuild') }}">@csrf<input type="hidden" name="exam_id" value="{{ $r->exam_id }}"><input type="hidden" name="subject_id" value="{{ $r->subject_id }}"><button class="btn btn-o btn-sm" type="submit">Rebuild</button></form>@endif</div></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty"><p class="h3">No packs yet</p><p class="small muted">A pack is made when an admin publishes a paper.</p></div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@endsection
