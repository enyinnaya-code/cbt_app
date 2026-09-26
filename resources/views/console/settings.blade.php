@extends('layouts.app')

@section('title', 'Site settings')
@section('page_class', 'narrow')

@section('content')
<div class="col"><h1 class="h1">Content</h1><p class="muted">Links and contact details shown on the public site.</p></div>
@include('console._content-tabs')

@if($errors->any())<div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('console.settings.update') }}" class="stack" style="gap:20px">
    @csrf @method('PUT')

    <section class="card stack" style="gap:14px">
        <h2 class="h2">App downloads</h2>
        <p class="small muted">These appear as buttons on the landing page. Leave a box empty to show "Coming soon".</p>
        <div class="field"><label for="app_play_store_url">Google Play link</label><input id="app_play_store_url" name="app_play_store_url" type="url" class="input" value="{{ old('app_play_store_url', $values['app.play_store_url']) }}" placeholder="https://play.google.com/store/apps/details?id=com.testacbt.app"></div>
        <div class="field"><label for="app_app_store_url">App Store link</label><input id="app_app_store_url" name="app_app_store_url" type="url" class="input" value="{{ old('app_app_store_url', $values['app.app_store_url']) }}" placeholder="https://apps.apple.com/app/..."></div>
        <div class="field"><label for="app_apk_url">Direct Android download (APK) <span class="muted">(optional)</span></label><input id="app_apk_url" name="app_apk_url" type="url" class="input" value="{{ old('app_apk_url', $values['app.apk_url']) }}" placeholder="https://"></div>
    </section>

    <section class="card stack" style="gap:14px">
        <h2 class="h2">Payments</h2>
        <div class="alert {{ $paystack ? 'ok' : 'info' }}">
            @if($paystack)Online payment with Paystack is on.@else Online payment with Paystack is off. To turn it on, put your Paystack secret key in the server's .env file as PAYSTACK_SECRET_KEY.@endif
            <span style="display:block;font-weight:500;margin-top:4px">In your Paystack dashboard (Settings, API Keys and Webhooks) set the webhook URL to <b>{{ url('/api/webhooks/paystack') }}</b></span>
        </div>
        <p class="small muted">Bank transfer shows the account below to students. Leave any box empty to turn bank transfer off.</p>
        <div class="grid2">
            <div class="field"><label for="bank_name">Bank</label><input id="bank_name" name="bank_name" class="input" value="{{ old('bank_name', $values['bank.name']) }}" maxlength="80"></div>
            <div class="field"><label for="bank_account_number">Account number</label><input id="bank_account_number" name="bank_account_number" class="input" inputmode="numeric" value="{{ old('bank_account_number', $values['bank.account_number']) }}" maxlength="20"></div>
        </div>
        <div class="field"><label for="bank_account_name">Account name</label><input id="bank_account_name" name="bank_account_name" class="input" value="{{ old('bank_account_name', $values['bank.account_name']) }}" maxlength="120"></div>
        <div class="field"><label for="bank_note">Extra instructions <span class="muted">(optional)</span></label><input id="bank_note" name="bank_note" class="input" value="{{ old('bank_note', $values['bank.note']) }}" maxlength="300" placeholder="e.g. Transfers made after 6pm are confirmed the next morning"></div>
    </section>

    <section class="card stack" style="gap:14px">
        <h2 class="h2">Pricing defaults</h2>
        <p class="small muted">Used for every subject that has no price of its own. You can set a different price for a subject under Exams.</p>
        <div class="grid3">
            <div class="field"><label for="pricing_default_price">Price per subject (₦)</label><input id="pricing_default_price" name="pricing_default_price" type="number" min="0" class="input" value="{{ old('pricing_default_price', $values['pricing.default_price']) }}" placeholder="{{ \App\Services\Pricing::DEFAULT_PRICE }}"></div>
            <div class="field"><label for="pricing_free_questions">Free questions per subject</label><input id="pricing_free_questions" name="pricing_free_questions" type="number" min="0" class="input" value="{{ old('pricing_free_questions', $values['pricing.free_questions']) }}" placeholder="{{ \App\Services\Pricing::DEFAULT_FREE_QUESTIONS }}"></div>
            <div class="field"><label for="pricing_access_days">A purchase lasts (days)</label><input id="pricing_access_days" name="pricing_access_days" type="number" min="1" class="input" value="{{ old('pricing_access_days', $values['pricing.access_days']) }}" placeholder="{{ \App\Services\Pricing::DEFAULT_ACCESS_DAYS }}"></div>
        </div>
    </section>

    <section class="card stack" style="gap:14px">
        <h2 class="h2">Support</h2>
        <div class="grid2">
            <div class="field"><label for="support_email">Support email</label><input id="support_email" name="support_email" type="email" class="input" value="{{ old('support_email', $values['support.email']) }}"></div>
            <div class="field"><label for="support_whatsapp">WhatsApp number</label><input id="support_whatsapp" name="support_whatsapp" class="input" value="{{ old('support_whatsapp', $values['support.whatsapp']) }}" placeholder="+234..."></div>
        </div>
    </section>

    <div><button class="btn btn-p" type="submit">Save settings</button></div>
</form>
@endsection
