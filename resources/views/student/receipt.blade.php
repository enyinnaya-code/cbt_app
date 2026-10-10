@extends('layouts.base')

@section('title', 'Receipt ' . $order->reference)
@section('robots', 'noindex, nofollow')

@section('body')
<main class="receipt-page">
    <div class="receipt-actions no-print">
        <a class="link small" href="{{ route('orders.show', $order->reference) }}">&larr; Back</a>
        <button type="button" class="btn btn-p btn-sm" onclick="window.print()">Print or save as PDF</button>
    </div>

    <article class="receipt">
        <header class="receipt-head">
            <div><p class="h2">TestaCBT</p><p class="small muted">Payment receipt</p></div>
            <div class="receipt-paid">PAID</div>
        </header>

        <dl class="receipt-meta">
            <div><dt>Receipt number</dt><dd>{{ $order->reference }}</dd></div>
            <div><dt>Date paid</dt><dd>{{ $order->paid_at->format('j F Y, g:i a') }}</dd></div>
            <div><dt>Paid by</dt><dd>{{ $user->name }}<br><span class="small muted">{{ $user->email }}</span></dd></div>
            <div><dt>How</dt><dd>{{ $order->methodLabel() }}</dd></div>
        </dl>

        <table class="receipt-table">
            <thead><tr><th>Item</th><th class="num">Amount</th></tr></thead>
            <tbody>
                <tr>
                    <td>
                        <b>{{ $order->exam->name }}</b>: {{ $order->is_bundle ? 'all subjects' : $order->items->pluck('subject.name')->implode(', ') }}
                        <br><span class="small muted">Full question access for {{ $order->access_days }} days, until {{ $until->format('j F Y') }}</span>
                    </td>
                    <td class="num">{{ \App\Services\Pricing::naira($order->amount) }}</td>
                </tr>
            </tbody>
            <tfoot><tr><th>Total paid</th><th class="num">{{ \App\Services\Pricing::naira($order->amount) }}</th></tr></tfoot>
        </table>

        <p class="small muted">Thank you for choosing TestaCBT. Keep this receipt for your records.@if($support = \App\Models\Setting::get('support.email')) Questions? Write to {{ $support }}.@endif</p>
        @include('layouts._credit')
    </article>
</main>
<style>
.receipt-page{max-width:720px;margin:0 auto;padding:20px 16px 40px}
.receipt-actions{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.receipt{background:var(--surface);border:1px solid var(--border);border-radius:20px;padding:26px;display:flex;flex-direction:column;gap:20px}
.receipt-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px}
.receipt-paid{border:2px solid var(--primary);color:var(--primary);font-weight:800;letter-spacing:.12em;padding:6px 14px;border-radius:10px;transform:rotate(-4deg)}
.receipt-meta{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin:0}
.receipt-meta dt{font-size:12px;color:var(--muted);text-transform:uppercase;letter-spacing:.06em}
.receipt-meta dd{margin:2px 0 0;font-weight:600;overflow-wrap:anywhere}
.receipt-table{width:100%;border-collapse:collapse}
.receipt-table th,.receipt-table td{padding:12px 6px;border-bottom:1px solid var(--border);text-align:left;vertical-align:top}
.receipt-table .num{text-align:right;white-space:nowrap}
.receipt-table tfoot th{border-bottom:0;font-size:17px}
@media (max-width:520px){.receipt-meta{grid-template-columns:1fr}}
@media print{body{background:#fff!important;color:#000!important}.no-print{display:none!important}.receipt{border:0;padding:0;box-shadow:none;background:#fff!important}.receipt *{color:#000!important}.credit{display:none}}
</style>
@endsection
