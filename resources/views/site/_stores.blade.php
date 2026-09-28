{{--
  App download buttons. Expects $stores (android, apk, apk_version, apk_size).
  Google Play when there is a link; otherwise the Android app can be downloaded from this website.
  Only shown when there is an app (see welcome.blade.php). The app is Android only; everyone else uses the website.
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
</div>
