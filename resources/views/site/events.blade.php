@extends('layouts.public')

@section('title', 'Upcoming events')

@section('content')
<section class="section" style="padding-top:12px;max-width:820px">
    <div class="section-head"><div><h1 class="h1">Upcoming events</h1><p>Exam dates, registration deadlines, result releases and webinars.</p></div></div>

    <div class="card">
        @forelse($upcoming as $e)
            @include('site._event', ['e' => $e])
        @empty
            <div class="empty"><span class="sq c-g"><x-icon name="calendar"/></span><p class="h3">No upcoming events</p><p class="small muted">New dates are added here as soon as they are announced.</p></div>
        @endforelse
    </div>

    @if($past->isNotEmpty())
        <h2 class="h2" style="margin:32px 0 12px">Recently passed</h2>
        <div class="card">@foreach($past as $e)@include('site._event', ['e' => $e])@endforeach</div>
    @endif
</section>
@endsection
