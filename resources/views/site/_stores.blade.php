{{--
  App download buttons. Expects $stores (android, ios, apk, apk_version, apk_size).
  Google Play and the App Store when there are links; otherwise the Android app can be downloaded from this website,
  and everyone can use the site as an app from their browser. Nothing says "coming soon".
--}}
@php
    $size = $stores['apk_size'] > 0 ? round($stores['apk_size'] / 1048576, $stores['apk_size'] >= 10485760 ? 0 : 1) . ' MB' : null;
    $apkNote = trim(($stores['apk_version'] ? 'Version ' . $stores['apk_version'] : '') . ($size ? ($stores['apk_version'] ? ' · ' : '') . $size : ''));
@endphp
<div class="store-row">
    @if($stores['android'])
        <a class="store-btn" href="{{ $stores['android'] }}" target="_blank" rel="noopener"><x-icon name="phone"/><span><small>Android</small><b>Google Play</b></span></a>
    @endif
    @if($stores['apk'])
        <a class="store-btn" href="{{ $stores['apk'] }}" download rel="noopener"><x-icon name="download"/><span><small>{{ $stores['android'] ? 'Or download the file' : 'Android' }}</small><b>Download the app (APK)</b></span></a>
    @elseif(! $stores['android'])
        <a class="store-btn" href="#install-android"><x-icon name="phone"/><span><small>Android</small><b>Use it on your phone</b></span></a>
    @endif

    @if($stores['ios'])
        <a class="store-btn" href="{{ $stores['ios'] }}" target="_blank" rel="noopener"><x-icon name="phone"/><span><small>iPhone</small><b>App Store</b></span></a>
    @else
        <a class="store-btn" href="#install-iphone"><x-icon name="phone"/><span><small>iPhone</small><b>Add to Home Screen</b></span></a>
    @endif
</div>

@if($stores['apk'] && $apkNote)<p class="small" style="margin-top:10px;opacity:.85">{{ $apkNote }}</p>@endif

<div class="install-help">
    @if($stores['apk'])
    <details id="install-apk">
        <summary>How to install the Android app</summary>
        <ol>
            <li>Tap <b>Download the app (APK)</b> and wait for the download to finish.</li>
            <li>Open the downloaded file. If Android asks, allow installing from your browser or Files app, just this once.</li>
            <li>Tap <b>Install</b>, then open TestaCBT and sign in with the same account you use on the website.</li>
        </ol>
    </details>
    @endif
    @if(! $stores['android'] && ! $stores['apk'])
    <details id="install-android">
        <summary>How to use TestaCBT on Android</summary>
        <ol>
            <li>Open <b>{{ parse_url(url('/'), PHP_URL_HOST) }}</b> in Chrome.</li>
            <li>Tap the <b>&#8942;</b> menu, then <b>Add to Home screen</b> (or <b>Install app</b>).</li>
            <li>Open TestaCBT from your home screen like any other app.</li>
        </ol>
    </details>
    @endif
    @if(! $stores['ios'])
    <details id="install-iphone">
        <summary>How to add TestaCBT to your iPhone</summary>
        <ol>
            <li>Open <b>{{ parse_url(url('/'), PHP_URL_HOST) }}</b> in <b>Safari</b>.</li>
            <li>Tap the <b>Share</b> button (the square with an arrow), then <b>Add to Home Screen</b>.</li>
            <li>Open TestaCBT from your home screen. It works like an app.</li>
        </ol>
    </details>
    @endif
</div>