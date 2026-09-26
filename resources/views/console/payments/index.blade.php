@extends('layouts.app')

@section('title', 'Payments')

@section('content')
<div class="col"><h1 class="h1">Payments</h1><p class="muted">Orders from students. Online payments confirm themselves; bank transfers wait here for you to check.</p></div>

@if($waiting > 0)
    <a class="card row between" href="{{ route('console.payments.index', ['status' => 'awaiting_confirmation']) }}" style="border-color:var(--accent)">
        <div class="row grow"><span class="sq c-a"><x-icon name="bank"/></span>
            <div><p class="h3">{{ $waiting }} bank {{ Str::plural('transfer', $waiting) }} to check</p><p class="small muted">Match the amount and reference on your bank statement, then approve.</p></div></div>
        <span class="link">Review</span>
    </a>
@endif

<div class="grid2">
    <div class="stat"><span class="small muted">Paid this month</span><span class="big">{{ \App\Services\Pricing::naira($revenue['month']) }}</span></div>
    <div class="stat"><span class="small muted">Paid in total</span><span class="big">{{ \App\Services\Pricing::naira($revenue['total']) }}</span></div>
</div>

<form method="GET" class="filters card" style="padding:14px">
    <div class="field"><label for="f-status">Status</label>
        <select id="f-status" name="status" class="input"><option value="">All</option>
            @foreach(['awaiting_confirmation' => 'Waiting for me', 'pending' => 'Not paid yet', 'paid' => 'Paid', 'rejected' => 'Not confirmed', 'failed' => 'Failed', 'cancelled' => 'Cancelled'] as $k => $v)<option value="{{ $k }}" @selected(($filters['status'] ?? null) === $k)>{{ $v }}</option>@endforeach</select></div>
    <div class="field"><label for="f-q">Reference, name or email</label><input id="f-q" name="q" class="input" value="{{ $filters['q'] ?? '' }}"></div>
    <div class="row"><button class="btn btn-o btn-sm" type="submit">Filter</button>@if(array_filter($filters))<a class="btn btn-t btn-sm" href="{{ route('console.payments.index') }}">Clear</a>@endif</div>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Reference</th><th>Student</th><th>Exam</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
        @forelse($orders as $o)
            <tr>
                <td><a class="link" href="{{ route('console.payments.show', $o) }}">{{ $o->reference }}</a></td>
                <td>{{ $o->user->name }}<br><span class="small muted">{{ $o->user->email }}</span></td>
                <td>{{ $o->exam->name }}{{ $o->is_bundle ? ' (bundle)' : '' }}</td>
                <td>{{ \App\Services\Pricing::naira($o->amount) }}</td>
                <td>{{ $o->methodLabel() }}</td>
                <td><span class="badge {{ $o->statusBadge() }}">{{ $o->statusLabel() }}</span></td>
                <td class="small muted">{{ $o->created_at->format('j M Y g:i a') }}</td>
                <td><div class="actions"><a class="btn btn-o btn-sm" href="{{ route('console.payments.show', $o) }}">Open</a></div></td>
            </tr>
        @empty
            <tr><td colspan="8"><div class="empty"><p class="h3">No orders</p><p class="small muted">They appear here when a student starts to unlock a subject.</p></div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $orders->links('vendor.pagination.testacbt') }}
@endsection
