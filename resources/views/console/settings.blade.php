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
        <h2 class="h2">Support</h2>
        <div class="grid2">
            <div class="field"><label for="support_email">Support email</label><input id="support_email" name="support_email" type="email" class="input" value="{{ old('support_email', $values['support.email']) }}"></div>
            <div class="field"><label for="support_whatsapp">WhatsApp number</label><input id="support_whatsapp" name="support_whatsapp" class="input" value="{{ old('support_whatsapp', $values['support.whatsapp']) }}" placeholder="+234..."></div>
        </div>
    </section>

    <div><button class="btn btn-p" type="submit">Save settings</button></div>
</form>
@endsection
