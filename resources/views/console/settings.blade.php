@extends('layouts.app')

@section('title', 'Site settings')
@section('page_class', 'narrow')

@section('content')
<div class="col"><h1 class="h1">Content</h1><p class="muted">Links and contact details shown on the public site.</p></div>
@include('console._content-tabs')

@if($errors->any())<div class="alert err" role="alert"><ul style="margin:0;padding-left:18px">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" enctype="multipart/form-data" action="{{ route('console.settings.update') }}" class="stack" style="gap:20px">
    @csrf @method('PUT')

    <section class="card stack" style="gap:14px">
        <h2 class="h2">App downloads</h2>
        <p class="small muted">These become the download buttons on the home page. Until the app is in a store, upload the Android app here and students can download it straight from your website.</p>
        <div class="field"><label for="app_play_store_url">Google Play link</label><input id="app_play_store_url" name="app_play_store_url" type="url" class="input" value="{{ old('app_play_store_url', $values['app.play_store_url']) }}" placeholder="https://play.google.com/store/apps/details?id=com.testacbt.app"></div>
        <div class="field"><label for="apk_file">Android app file (.apk)</label>
            @if($apk['chosen'])
                <div class="alert ok" style="font-weight:500">Offered on the website now: <b>{{ $apk['chosen'] === $apk['upload'] ? 'the file uploaded here' : 'the release build that came with the code' }}</b>, {{ \App\Http\Controllers\Console\SettingsController::formatBytes($apk['chosen']['size']) }}@if($apk['chosen']['version']), version {{ $apk['chosen']['version'] }}@endif. <a class="link" href="{{ $apk['chosen']['url'] }}">Download it to check</a>.
                    <span style="display:block;font-weight:500;margin-top:4px" class="small">The newest file wins: a new release pulled from GitHub replaces an older upload, and a newer upload replaces the release.</span>
                    @if($apk['upload'])<label class="row small" style="gap:8px;margin-top:6px"><input type="checkbox" name="remove_apk" value="1"> Remove the file uploaded here</label>@endif</div>
            @endif
            <input id="apk_file" name="apk_file" type="file" class="input" accept=".apk,application/vnd.android.package-archive" style="padding:10px 14px">
            <span class="hint">Choosing a file replaces the current one. Up to 300 MB. This server accepts uploads up to <b>{{ \App\Http\Controllers\Console\SettingsController::formatBytes($uploadLimit) }}</b>{{ $uploadLimit > 0 && $uploadLimit < 100 * 1048576 ? ', so a bigger app needs the server limit raised (or use the link box below)' : '' }}.</span></div>
        <div class="field" id="apk-pieces" data-url="{{ route('console.settings.apk.piece') }}" data-token="{{ csrf_token() }}">
            <label for="apk_pieces_file">Or upload in pieces <span class="muted">(works on any server, even when the upload limit is small)</span></label>
            <input id="apk_pieces_file" type="file" class="input" accept=".apk,application/vnd.android.package-archive" style="padding:10px 14px">
            <div class="row" style="gap:10px;align-items:center"><button type="button" class="btn btn-o btn-sm" id="apk-pieces-go">Upload now</button>
                <span class="small muted" id="apk-pieces-note">Uploads straight away, using the version typed below. No need to press Save.</span></div>
            <div style="height:8px;border-radius:6px;background:var(--surface-2);overflow:hidden;display:none" id="apk-pieces-bar"><div style="height:100%;width:0;background:var(--primary);transition:width .2s" id="apk-pieces-fill"></div></div>
        </div>
        <div class="grid2">
            <div class="field"><label for="app_apk_version">App version <span class="muted">(shown next to the button)</span></label><input id="app_apk_version" name="app_apk_version" class="input" value="{{ old('app_apk_version', $values['app.apk_version']) }}" maxlength="20" placeholder="1.0.0"></div>
            <div class="field"><label for="app_apk_url">Or a link to the file hosted elsewhere <span class="muted">(optional)</span></label><input id="app_apk_url" name="app_apk_url" type="url" class="input" value="{{ old('app_apk_url', $values['app.apk_url']) }}" placeholder="https://"></div>
        </div>
    </section>

    <section class="card stack" style="gap:14px">
        <h2 class="h2">Payments</h2>
        <div class="alert {{ $paystack ? 'ok' : 'info' }}">
            @if($paystack)Online payment with Paystack is on.@else Online payment with Paystack is off. To turn it on, save your Paystack secret key in the <a class="link" href="#paystack">Paystack section</a> below.@endif
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
        <h2 class="h2">Search engines and social media</h2>
        <p class="small muted">Paste the verification code Google or Bing gives you (just the code, not the whole tag) to prove the site is yours in Search Console or Webmaster Tools. Your sitemap is <b>{{ url('/sitemap.xml') }}</b>. Social page links help search engines connect your pages to them.</p>
        <div class="grid2">
            <div class="field"><label for="seo_google_verification">Google Search Console code</label><input id="seo_google_verification" name="seo_google_verification" class="input" value="{{ old('seo_google_verification', $values['seo.google_verification']) }}" maxlength="120"></div>
            <div class="field"><label for="seo_bing_verification">Bing Webmaster code</label><input id="seo_bing_verification" name="seo_bing_verification" class="input" value="{{ old('seo_bing_verification', $values['seo.bing_verification']) }}" maxlength="120"></div>
        </div>
        <div class="grid2">
            <div class="field"><label for="social_facebook">Facebook page</label><input id="social_facebook" name="social_facebook" type="url" class="input" value="{{ old('social_facebook', $values['social.facebook']) }}" placeholder="https://facebook.com/..."></div>
            <div class="field"><label for="social_x">X (Twitter) profile</label><input id="social_x" name="social_x" type="url" class="input" value="{{ old('social_x', $values['social.x']) }}" placeholder="https://x.com/..."></div>
            <div class="field"><label for="social_instagram">Instagram</label><input id="social_instagram" name="social_instagram" type="url" class="input" value="{{ old('social_instagram', $values['social.instagram']) }}" placeholder="https://instagram.com/..."></div>
            <div class="field"><label for="social_youtube">YouTube</label><input id="social_youtube" name="social_youtube" type="url" class="input" value="{{ old('social_youtube', $values['social.youtube']) }}" placeholder="https://youtube.com/..."></div>
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

<section class="card stack" style="gap:14px" id="paystack">
    <div>
        <h2 class="h2">Paystack key</h2>
        <p class="small muted">The secret key for taking card, bank and USSD payments. Find it in your Paystack dashboard under Settings, then API Keys &amp; Webhooks. It is kept encrypted and never shown again.</p>
        <p class="small">
            @if($paystackKey['on'])
                <span class="badge {{ $paystackKey['mode'] === 'live' ? 'g' : '' }}">{{ $paystackKey['mode'] === 'live' ? 'Live key' : ($paystackKey['mode'] === 'test' ? 'Test key' : 'Key set') }}</span>
                In use: <b>sk_&hellip;{{ $paystackKey['tail'] }}</b> ({{ $paystackKey['source'] === 'settings' ? 'saved here' : 'from the server\'s .env file' }}).
                @if($paystackKey['mode'] === 'test') Test payments use no real money.@endif
            @else
                <span class="badge">Not set</span> Students can only pay by bank transfer until you save a key.
            @endif
        </p>
    </div>

    <form method="POST" action="{{ route('console.settings.paystack') }}" class="stack" style="gap:14px" autocomplete="off">
        @csrf @method('PUT')
        <div class="field">
            <label for="paystack_secret">{{ $paystackKey['on'] ? 'Replace the secret key' : 'Secret key' }}</label>
            <input id="paystack_secret" name="paystack_secret" type="password" class="input" required autocomplete="new-password" placeholder="sk_live_... or sk_test_..." spellcheck="false">
            <p class="small muted">Use a <b>test</b> key (sk_test_) to try it without real money, then the <b>live</b> key (sk_live_) when you are ready.</p>
        </div>
        <div class="row wrap" style="gap:10px"><button class="btn btn-p" type="submit">Save key</button></div>
    </form>

    <div class="alert info" style="margin:0">
        Also set the webhook in your Paystack dashboard (test and live are separate): <b>{{ url('/api/webhooks/paystack') }}</b><br>
        Callback address (optional, the site sends its own): <b>{{ route('checkout.callback') }}</b>
    </div>

    @if($paystackKey['on'])
    <div class="row wrap" style="gap:10px">
        <form method="POST" action="{{ route('console.settings.paystack.check') }}">@csrf <button class="btn btn-o" type="submit">Check the key</button></form>
        @if($paystackKey['source'] === 'settings')
        <form method="POST" action="{{ route('console.settings.paystack') }}">@csrf @method('PUT') <input type="hidden" name="forget_paystack" value="1"><button class="btn btn-d" type="submit" data-confirm="Online payment stops working unless the server's .env file has a key." data-confirm-title="Remove the saved Paystack key?" data-confirm-label="Remove" data-confirm-tone="danger">Remove the saved key</button></form>
        @endif
    </div>
    @endif
</section>

<section class="card stack" style="gap:14px" id="email">
    <div>
        <h2 class="h2">Email account</h2>
        <p class="small muted">The account TestaCBT sends email from, such as password reset links. Use the details your email provider gives you (for example Gmail, Zoho, or your host). You can change them here at any time.</p>
        <p class="small">
            @if($mail['mail.host'] !== '' && $mail['mail.from_address'] !== '')
                <span class="badge g">In use</span> Emails go out through <b>{{ $mail['mail.host'] }}</b> as <b>{{ $mail['mail.from_address'] }}</b>.
            @else
                <span class="badge">Not set</span> Until you save an account, emails are only written to a file on the server and nobody receives them.
            @endif
        </p>
    </div>

    <form method="POST" action="{{ route('console.settings.mail') }}" class="stack" style="gap:14px" autocomplete="off">
        @csrf @method('PUT')
        <div class="grid2">
            <div class="field"><label for="mail_host">Mail server (host)</label><input id="mail_host" name="mail_host" class="input" required value="{{ old('mail_host', $mail['mail.host']) }}" placeholder="smtp.gmail.com" autocomplete="off"></div>
            <div class="field"><label for="mail_port">Port</label><input id="mail_port" name="mail_port" type="number" min="1" max="65535" class="input" required value="{{ old('mail_port', $mail['mail.port'] ?: 587) }}"></div>
        </div>
        <div class="grid2">
            <div class="field"><label for="mail_encryption">Security</label>
                <select id="mail_encryption" name="mail_encryption" class="input">
                    @foreach($mailEncryptions as $value => $label)<option value="{{ $value }}" @selected(old('mail_encryption', $mail['mail.encryption'] ?: 'tls') === $value)>{{ $label }}</option>@endforeach
                </select></div>
            <div class="field"><label for="mail_username">Username</label><input id="mail_username" name="mail_username" class="input" value="{{ old('mail_username', $mail['mail.username']) }}" autocomplete="off" placeholder="Usually your email address"></div>
        </div>
        <div class="field">
            <label for="mail_password">Password</label>
            <input id="mail_password" name="mail_password" type="password" class="input" autocomplete="new-password" placeholder="{{ $mail['mail.has_password'] ? 'A password is saved. Leave empty to keep it.' : 'The email account password or app password' }}">
            <p class="small muted">It is kept encrypted and never shown again. For Gmail use an app password, not your normal one.</p>
            @if($mail['mail.has_password'])<label class="row small" style="gap:8px"><input type="checkbox" name="clear_mail_password" value="1"> Remove the saved password</label>@endif
        </div>
        <div class="grid2">
            <div class="field"><label for="mail_from_address">Send emails from</label><input id="mail_from_address" name="mail_from_address" type="email" class="input" required value="{{ old('mail_from_address', $mail['mail.from_address']) }}" placeholder="no-reply@yourdomain.com"></div>
            <div class="field"><label for="mail_from_name">Sender name</label><input id="mail_from_name" name="mail_from_name" class="input" value="{{ old('mail_from_name', $mail['mail.from_name']) }}" placeholder="TestaCBT"></div>
        </div>
        <div class="row wrap" style="gap:10px"><button class="btn btn-p" type="submit">Save email settings</button></div>
    </form>

    @if($mail['mail.host'] !== '')
    <div class="row wrap" style="gap:10px">
        <form method="POST" action="{{ route('console.settings.mail.test') }}">@csrf <button class="btn btn-o" type="submit">Send a test email to me</button></form>
        <form method="POST" action="{{ route('console.settings.mail') }}">@csrf @method('PUT') <input type="hidden" name="forget_mail" value="1"><button class="btn btn-d" type="submit" data-confirm="Emails will stop going out through this account until you set one again." data-confirm-title="Remove the email account?" data-confirm-label="Remove" data-confirm-tone="danger">Remove this account</button></form>
    </div>
    @endif
</section>
<script>
(function () {
    var box = document.getElementById('apk-pieces');
    if (!box) { return; }
    var fileInput = document.getElementById('apk_pieces_file'), go = document.getElementById('apk-pieces-go');
    var note = document.getElementById('apk-pieces-note'), bar = document.getElementById('apk-pieces-bar'), fill = document.getElementById('apk-pieces-fill');
    var PIECE = 1024 * 1024;

    function say(text, bad) { note.textContent = text; note.style.color = bad ? 'var(--danger)' : ''; }
    function newId() { var a = new Uint8Array(16); crypto.getRandomValues(a); return Array.prototype.map.call(a, function (x) { return ('0' + x.toString(16)).slice(-2); }).join(''); }

    function sendPiece(id, file, index, total, version, tries) {
        var form = new FormData();
        form.append('upload_id', id); form.append('index', index); form.append('total', total); form.append('version', version);
        form.append('piece', file.slice(index * PIECE, (index + 1) * PIECE), 'piece');
        return fetch(box.getAttribute('data-url'), { method: 'POST', body: form, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': box.getAttribute('data-token'), 'Accept': 'application/json' } })
            .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { return { ok: r.ok, body: j }; }); })
            .catch(function () { return { ok: false, body: {}, network: true }; })
            .then(function (res) {
                if (res.ok) { return res.body; }
                if (res.network && tries < 3) { say('Connection dropped, trying again...'); return sendPiece(id, file, index, total, version, tries + 1); }
                var m = res.body && (res.body.message || (res.body.errors && Object.values(res.body.errors)[0][0]));
                throw new Error(m || 'The upload stopped. Check your connection and try again.');
            });
    }

    go.addEventListener('click', function () {
        var file = fileInput.files[0];
        if (!file) { say('Choose the .apk file first.', true); return; }
        if (!/\.apk$/i.test(file.name)) { say('That is not an .apk file.', true); return; }
        var total = Math.ceil(file.size / PIECE), id = newId(), version = document.getElementById('app_apk_version').value;
        go.disabled = true; fileInput.disabled = true; bar.style.display = 'block'; fill.style.width = '0';
        say('Uploading...');
        var i = 0;
        (function next() {
            sendPiece(id, file, i, total, version, 0).then(function (r) {
                i++;
                fill.style.width = Math.round(i / total * 100) + '%';
                if (r.done) { say('Done: ' + r.size + ' is now on the website. Reloading...'); setTimeout(function () { location.reload(); }, 1200); return; }
                say('Uploading... ' + Math.round(i / total * 100) + '%');
                next();
            }).catch(function (e) { say(e.message, true); go.disabled = false; fileInput.disabled = false; });
        })();
    });
})();
</script>
@endsection
