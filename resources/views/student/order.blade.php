@extends('layouts.app')

@section('title', 'Order ' . $order->reference)
@section('page_class', 'narrow')

@section('content')
<div class="col">
    <a class="link small" href="{{ route('orders.index') }}">&larr; My purchases</a>
    <h1 class="h1">{{ \App\Services\Pricing::naira($order->amount) }} <span class="badge {{ $order->statusBadge() }}" style="vertical-align:middle">{{ $order->statusLabel() }}</span></h1>
    <p class="muted">{{ $order->exam->name }}: {{ $order->is_bundle ? 'all subjects' : $order->items->pluck('subject.name')->implode(', ') }} &middot; {{ $order->access_days }} days</p>
</div>

@if($errors->any())<div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

@if($order->isPaid())
    <div class="card row between wrap" style="background:var(--primary-soft);border:0">
        <div><p class="h3">Unlocked</p><p class="small">Paid {{ $order->paid_at->format('j F Y g:i a') }}. Your subjects now have every question.</p></div>
        <a class="btn btn-p" href="{{ route('practice.index', ['exam' => $order->exam->slug]) }}">Start practising</a>
    </div>
@elseif($order->status === 'rejected')
    <div class="alert err" role="alert"><p><b>We could not confirm this transfer.</b></p>@if($order->decision_note)<p style="font-weight:500;margin-top:4px">{{ $order->decision_note }}</p>@endif</div>
    <p class="small muted">If you did pay, contact us with your reference {{ $order->reference }}. Otherwise you can start again.</p>
    <div><a class="btn btn-p btn-sm" href="{{ route('checkout.show', ['exam' => $order->exam->slug]) }}">Start again</a></div>
@elseif($order->status === 'failed' || $order->status === 'cancelled')
    <p class="muted">This order was not completed and you have not been charged.</p>
    <div><a class="btn btn-p btn-sm" href="{{ route('checkout.show', ['exam' => $order->exam->slug]) }}">Try again</a></div>
@elseif($order->method === 'bank_transfer')
    @if($bank)
    <section class="card stack" style="gap:12px">
        <h2 class="h2">1. Send {{ \App\Services\Pricing::naira($order->amount) }} to this account</h2>
        <div class="facts">
            <div><small>Bank</small><b>{{ $bank['bank'] }}</b></div>
            <div><small>Account number</small><b>{{ $bank['number'] }}</b></div>
            <div><small>Account name</small><b>{{ $bank['name'] }}</b></div>
            <div><small>Amount</small><b>{{ \App\Services\Pricing::naira($order->amount) }}</b></div>
        </div>
        <div class="alert info">Put <b>{{ $order->reference }}</b> in the transfer description, so we can find your payment.</div>
        @if($bank['note'])<p class="small muted">{{ $bank['note'] }}</p>@endif
    </section>
    @else
    <div class="alert err">Bank details are not available right now. Please contact support.</div>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ route('orders.proof', $order->reference) }}" class="card stack" style="gap:14px">
        @csrf
        <h2 class="h2">{{ $order->status === 'awaiting_confirmation' ? 'We are checking your transfer' : '2. Tell us you have paid' }}</h2>
        @if($order->status === 'awaiting_confirmation')<p class="small muted">Sent {{ $order->submitted_at->format('j M Y g:i a') }}. You can add or change the details below if needed.</p>@endif
        <div class="field"><label for="payer_name">Name on the account you sent from</label><input id="payer_name" name="payer_name" class="input" value="{{ old('payer_name', $order->payer_name) }}" maxlength="120" required></div>
        <div class="field"><label for="proof">Receipt <span class="muted">(optional, picture or PDF up to 4 MB)</span></label><input id="proof" name="proof" type="file" class="input" accept="image/jpeg,image/png,image/webp,application/pdf" style="padding:10px 14px"></div>
        <div class="field"><label for="note">Anything else? <span class="muted">(optional)</span></label><textarea id="note" name="note" class="input" rows="2" maxlength="500" style="padding:12px 14px">{{ old('note', $order->note) }}</textarea></div>
        <div><button class="btn btn-p" type="submit">{{ $order->status === 'awaiting_confirmation' ? 'Update details' : 'I have paid' }}</button></div>
    </form>

    <form method="POST" action="{{ route('orders.cancel', $order->reference) }}">@csrf
        <button class="btn btn-t btn-sm" type="submit" data-confirm="Cancel this order?">Cancel this order</button></form>
@else
    <div class="card stack" style="gap:12px">
        <p class="h3">We are waiting for Paystack to confirm your payment.</p>
        <p class="small muted">If you have paid, this page will update. It can take a minute. If you were charged and nothing unlocks within an hour, contact us with the reference {{ $order->reference }}.</p>
        <div class="row"><a class="btn btn-o btn-sm" href="{{ route('orders.show', $order->reference) }}">Check again</a>
            <form method="POST" action="{{ route('orders.cancel', $order->reference) }}">@csrf<button class="btn btn-t btn-sm" type="submit" data-confirm="Cancel this order? Only do this if you did not pay.">Cancel</button></form></div>
    </div>
@endif

<p class="tiny muted">Reference {{ $order->reference }} &middot; {{ $order->methodLabel() }} &middot; {{ $order->created_at->format('j M Y g:i a') }}</p>
@endsection
