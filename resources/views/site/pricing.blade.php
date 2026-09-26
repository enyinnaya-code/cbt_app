@extends('layouts.public')

@section('title', 'Pricing: free questions in every subject')
@section('meta_description', 'Try free questions in every subject, then unlock only the subjects you need. Pay once by card or bank transfer, no subscription.')

@php $tones = ['c-a', 'c-b', 'c-p', 'c-g']; @endphp

@section('content')
<section class="section" style="padding-top:12px;max-width:880px">
    <div class="section-head"><div><h1 class="h1">Start free. Unlock what you need.</h1>
        <p>Every subject has free questions to try. When you want all of them, unlock just that subject. No subscription: pay once, use it for {{ $days }} days.</p></div></div>

    @if($exams->isNotEmpty())
    <div class="seg" role="tablist" aria-label="Exam" style="margin-bottom:18px">
        @foreach($exams as $e)<a href="{{ route('pricing', ['exam' => $e->slug]) }}" class="{{ $exam && $exam->id === $e->id ? 'on' : '' }}" role="tab">{{ $e->name }}</a>@endforeach
    </div>
    @endif

    @if($rows->isEmpty())
        <div class="card empty"><span class="sq c-a"><x-icon name="book"/></span><p class="h3">{{ $exam ? $exam->name . ' questions are coming soon' : 'Nothing here yet' }}</p><p class="small muted">Check back soon.</p></div>
    @else
        <div class="card" style="padding:6px 20px">
            @foreach($rows as $i => $r)
                <div class="li" style="flex-wrap:wrap">
                    <span class="sq {{ $tones[$i % 4] }}">{{ $r->code }}</span>
                    <div class="grow" style="min-width:160px">
                        <p class="h3">{{ $r->name }}</p>
                        <p class="small muted">{{ number_format($r->total) }} {{ Str::plural('question', $r->total) }}@if($r->price > 0) &middot; {{ min($r->free, $r->total) }} free @endif</p>
                    </div>
                    @if($r->price === 0)<span class="badge g">Free</span>
                    @elseif($r->expires)<span class="badge g">Unlocked until {{ $r->expires->format('j M Y') }}</span>
                    @else<span class="h3">{{ \App\Services\Pricing::naira($r->price) }}</span>@endif
                </div>
            @endforeach
        </div>

        @if($bundle)
            <div class="card row between wrap" style="margin-top:16px;background:var(--primary-soft);border:0">
                <div><p class="h3">All {{ $exam->name }} subjects for {{ \App\Services\Pricing::naira($bundle) }}</p>
                    <p class="small muted">@if($bundleSaves > 0)You save {{ \App\Services\Pricing::naira($bundleSaves) }} compared with buying them one by one.@else Every subject in one payment.@endif</p></div>
                <a class="btn btn-p" href="{{ auth()->check() ? route('checkout.show', ['exam' => $exam->slug]) : route('register') }}">Get the bundle</a>
            </div>
        @endif

        <div class="row wrap" style="margin-top:20px">
            @auth
                <a class="btn btn-p" href="{{ route('checkout.show', ['exam' => $exam->slug]) }}"><x-icon name="lock" size="s"/>Unlock subjects</a>
            @else
                <a class="btn btn-p" href="{{ route('register') }}">Create a free account</a>
                <a class="btn btn-o" href="{{ route('login') }}">I already have an account</a>
            @endauth
        </div>
    @endif

    <div class="card flat stack" style="margin-top:28px;gap:10px">
        <h2 class="h3">How paying works</h2>
        <p class="small muted">Pay online with your card, bank or USSD through Paystack, or send a bank transfer and tell us when it is done. Online payments unlock straight away; transfers are checked by a person, usually within a few hours. Your unlocked subjects work on the website and in the mobile app, including offline.</p>
    </div>
</section>
@endsection
