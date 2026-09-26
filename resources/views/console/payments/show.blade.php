@extends('layouts.app')

@section('title', 'Order ' . $order->reference)
@section('page_class', 'mid')

@section('content')
<div class="col">
    <a class="link small" href="{{ route('console.payments.index') }}">&larr; Payments</a>
    <h1 class="h1">{{ \App\Services\Pricing::naira($order->amount) }} <span class="badge {{ $order->statusBadge() }}" style="vertical-align:middle">{{ $order->statusLabel() }}</span></h1>
    <p class="muted">{{ $order->reference }} &middot; {{ $order->methodLabel() }} &middot; started {{ $order->created_at->format('j M Y g:i a') }}</p>
</div>

<div class="card facts">
    <div><small>Student</small><b>{{ $order->user->name }}</b><br><span class="small muted">{{ $order->user->email }}@if($order->user->phone) &middot; {{ $order->user->phone }}@endif</span></div>
    <div><small>Exam</small><b>{{ $order->exam->name }}{{ $order->is_bundle ? ' (bundle)' : '' }}</b></div>
    <div><small>Access</small><b>{{ $order->access_days }} days</b></div>
    @if($order->paid_at)<div><small>Paid</small><b>{{ $order->paid_at->format('j M Y g:i a') }}</b>@if($order->decider)<br><span class="small muted">Approved by {{ $order->decider->name }}</span>@endif</div>@endif
</div>

<section class="card">
    <h2 class="h2" style="margin-bottom:4px">Subjects</h2>
    @foreach($order->items as $item)
        <div class="li"><span class="grow h3">{{ $item->subject->name }}</span><span class="small muted">{{ \App\Services\Pricing::naira($item->list_price) }}</span></div>
    @endforeach
</section>

@if($order->method === 'bank_transfer')
<section class="card stack" style="gap:10px">
    <h2 class="h2">Transfer details from the student</h2>
    @if($order->submitted_at)
        <p><b>Sent from:</b> {{ $order->payer_name }}</p>
        @if($order->note)<p><b>Note:</b> {{ $order->note }}</p>@endif
        <p class="small muted">Said they paid on {{ $order->submitted_at->format('j M Y g:i a') }}.</p>
        @if($order->proof_path)<div><a class="btn btn-o btn-sm" href="{{ route('console.payments.proof', $order) }}" target="_blank" rel="noopener"><x-icon name="file" size="s"/>Open receipt</a></div>@endif
    @else
        <p class="muted">The student has not said they paid yet.</p>
    @endif
    @if($order->decision_note)<p class="small"><b>Reason given for not confirming:</b> {{ $order->decision_note }}</p>@endif
</section>
@endif

@if($errors->any())<div class="alert err" role="alert">{{ $errors->first() }}</div>@endif

@unless($order->isPaid())
<section class="card stack" style="gap:14px">
    <h2 class="h2">Decision</h2>
    <p class="small muted">Check your bank statement for {{ \App\Services\Pricing::naira($order->amount) }} with the reference {{ $order->reference }} before approving. Approving opens the subjects at once.</p>
    <div class="row wrap">
        <form method="POST" action="{{ route('console.payments.approve', $order) }}">@csrf
            <button class="btn btn-p" type="submit" data-confirm="Confirm that {{ \App\Services\Pricing::naira($order->amount) }} has arrived, and unlock these subjects for {{ $order->user->name }}?">Approve and unlock</button></form>
    </div>
    @if($order->method === 'bank_transfer' && $order->status !== 'rejected')
    <form method="POST" action="{{ route('console.payments.reject', $order) }}" class="stack" style="gap:10px">@csrf
        <div class="field"><label for="reason">Not received? Tell the student why</label><input id="reason" name="reason" class="input" maxlength="300" placeholder="e.g. We did not find this amount in our account"></div>
        <div><button class="btn btn-d btn-sm" type="submit">Mark as not confirmed</button></div>
    </form>
    @endif
</section>
@endunless
@endsection
