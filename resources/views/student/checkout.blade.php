@extends('layouts.app')

@section('title', 'Unlock subjects')
@section('page_class', 'mid')

@php
    $tones = ['c-a', 'c-b', 'c-p', 'c-g'];
    $anyMethod = $methods['paystack'] || $methods['bank_transfer'];
    $buyable = $catalog->where('buyable', true);
@endphp

@section('content')
<div class="col">
    <h1 class="h1">Unlock subjects</h1>
    <p class="muted">Every subject has free questions. Unlock a subject to use all of its questions for {{ \App\Services\Pricing::accessDays() }} days, on the website and in the app.</p>
</div>

@if($errors->any())<div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

@if($exams->isNotEmpty())
<div class="seg" role="tablist" aria-label="Exam">
    @foreach($exams as $e)<a href="{{ route('checkout.show', ['exam' => $e->slug]) }}" class="{{ $exam && $exam->id === $e->id ? 'on' : '' }}" role="tab">{{ $e->name }}</a>@endforeach
</div>
@endif

@if($catalog->isEmpty())
    <div class="card empty"><span class="sq c-a"><x-icon name="book"/></span><p class="h3">Nothing to unlock for {{ $exam?->name }} yet</p><p class="small muted">Either its subjects are free, or the questions are still being added.</p>
        <a class="btn btn-p btn-sm" href="{{ route('practice.index', ['exam' => $exam?->slug]) }}">Go to Practice</a></div>
@elseif(! $anyMethod)
    <div class="card empty"><span class="sq c-a"><x-icon name="card"/></span><p class="h3">Payments are not open yet</p><p class="small muted">We are setting up payment. Please check back soon. You can keep practising the free questions.</p></div>
@else
<form method="POST" action="{{ route('checkout.store') }}" class="stack" style="gap:22px" id="checkout" data-bundle="{{ $bundle ?? 0 }}">
    @csrf
    <input type="hidden" name="exam" value="{{ $exam->slug }}">

    <section class="col" style="gap:12px">
        <h2 class="h3">Choose subjects</h2>
        <div class="card" style="padding:6px 20px">
            @foreach($catalog as $i => $s)
                <label class="li" style="cursor:{{ $s->buyable ? 'pointer' : 'default' }};flex-wrap:wrap;{{ $s->buyable ? '' : 'opacity:.7' }}">
                    <span class="sq {{ $tones[$i % 4] }}">{{ $s->code }}</span>
                    <span class="grow" style="min-width:150px">
                        <span class="h3" style="display:block">{{ $s->name }}</span>
                        <span class="small muted">{{ number_format($s->total) }} questions
                            @if($s->expires)&middot; unlocked until {{ $s->expires->format('j M Y') }}@if($s->renewal) (renew to add {{ \App\Services\Pricing::accessDays() }} days)@endif
                            @else&middot; {{ min($s->free, $s->total) }} free @endif</span>
                    </span>
                    <span class="h3">{{ \App\Services\Pricing::naira($s->price) }}</span>
                    @if($s->buyable)<input type="checkbox" class="pick" name="subjects[]" value="{{ $s->slug }}" data-price="{{ $s->price }}" @checked(in_array($s->slug, $picked, true) || in_array($s->slug, (array) old('subjects', []), true)) style="width:22px;height:22px">
                    @else<span class="badge g"><x-icon name="check" size="s"/>Unlocked</span>@endif
                </label>
            @endforeach
        </div>

        @if($bundle && $buyable->count() > 1)
            <label class="card row between wrap" style="background:var(--primary-soft);border:0;cursor:pointer">
                <span><span class="h3" style="display:block">All {{ $exam->name }} subjects: {{ \App\Services\Pricing::naira($bundle) }}</span>
                    <span class="small muted">Best value. Includes every subject above, whether or not you have some already.</span></span>
                <input type="checkbox" name="bundle" value="1" id="bundle" style="width:22px;height:22px" @checked(old('bundle'))>
            </label>
        @endif
    </section>

    <section class="col" style="gap:12px">
        <h2 class="h3">How would you like to pay?</h2>
        <div class="stack" style="gap:10px">
            @if($methods['paystack'])
                <label class="card row" style="gap:14px;cursor:pointer"><input type="radio" name="method" value="paystack" @checked(old('method', 'paystack') === 'paystack') style="width:20px;height:20px"><span class="sq c-g"><x-icon name="card"/></span>
                    <span><span class="h3" style="display:block">Card, bank or USSD</span><span class="small muted">Pay securely with Paystack. Unlocks straight away.</span></span></label>
            @endif
            @if($methods['bank_transfer'])
                <label class="card row" style="gap:14px;cursor:pointer"><input type="radio" name="method" value="bank_transfer" @checked(old('method', $methods['paystack'] ? 'paystack' : 'bank_transfer') === 'bank_transfer') style="width:20px;height:20px"><span class="sq c-b"><x-icon name="bank"/></span>
                    <span><span class="h3" style="display:block">Bank transfer</span><span class="small muted">Send the money from your bank app, then tell us. We check it and unlock, usually within a few hours.</span></span></label>
            @endif
        </div>
    </section>

    <div class="card flat row between wrap">
        <div><p class="small muted">Total</p><p class="h1" id="total">{{ \App\Services\Pricing::naira(0) }}</p></div>
        <button class="btn btn-p" type="submit" id="pay" disabled>Continue to payment</button>
    </div>
</form>
@endif
@endsection

@push('scripts')
<script>
(function () {
  var form = document.getElementById('checkout');
  if (!form) { return; }
  var boxes = Array.prototype.slice.call(form.querySelectorAll('input.pick'));
  var bundle = document.getElementById('bundle');
  var bundlePrice = parseInt(form.dataset.bundle, 10) || 0;
  var total = document.getElementById('total'), pay = document.getElementById('pay');

  function naira(n) { return '₦' + n.toLocaleString('en-NG'); }

  function sync() {
    var useBundle = bundle && bundle.checked;
    boxes.forEach(function (b) { if (useBundle) { b.checked = true; } b.disabled = !!useBundle; });
    var sum = useBundle ? bundlePrice : boxes.reduce(function (s, b) { return s + (b.checked ? parseInt(b.dataset.price, 10) : 0); }, 0);
    total.textContent = naira(sum);
    pay.disabled = sum <= 0;
    // A disabled checkbox is not submitted; the server prices a bundle on its own, so nothing is lost.
  }

  boxes.forEach(function (b) { b.addEventListener('change', sync); });
  if (bundle) { bundle.addEventListener('change', sync); }
  sync();
})();
</script>
@endpush
