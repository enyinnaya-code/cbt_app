@extends('layouts.app')

@section('title', 'My purchases')
@section('page_class', 'mid')

@section('content')
<div class="row between wrap">
    <div class="col"><h1 class="h1">My purchases</h1><p class="muted">What you have unlocked, and your payments.</p></div>
    <a class="btn btn-p" href="{{ route('checkout.show') }}"><x-icon name="lock" size="s"/>Unlock subjects</a>
</div>

<section class="card">
    <h2 class="h2" style="margin-bottom:4px">Unlocked</h2>
    @forelse($entitlements as $e)
        <div class="li">
            <div class="grow"><p class="h3">{{ $e->subject->name }}</p><p class="small muted">{{ $e->exam->name }}</p></div>
            <span class="small muted">until {{ $e->expires_at->format('j M Y') }}</span>
        </div>
    @empty
        <div class="empty"><p class="small muted">Nothing unlocked yet. Every subject still has free questions to try.</p></div>
    @endforelse
</section>

<section class="card">
    <h2 class="h2" style="margin-bottom:4px">Payments</h2>
    @forelse($orders as $o)
        <a class="li" href="{{ route('orders.show', $o->reference) }}" style="text-decoration:none;color:inherit;flex-wrap:wrap">
            <div class="grow" style="min-width:180px"><p class="h3">{{ $o->exam->name }}: {{ $o->is_bundle ? 'all subjects' : $o->items->pluck('subject.name')->implode(', ') }}</p>
                <p class="small muted">{{ $o->reference }} &middot; {{ $o->created_at->format('j M Y') }} &middot; {{ $o->methodLabel() }}</p></div>
            <span class="badge {{ $o->statusBadge() }}">{{ $o->statusLabel() }}</span>
            <span class="h3">{{ \App\Services\Pricing::naira($o->amount) }}</span>
        </a>
    @empty
        <div class="empty"><p class="small muted">No payments yet.</p></div>
    @endforelse
</section>
@endsection
