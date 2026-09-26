{{-- App download buttons. Expects $stores (android, ios, apk). A store that has no link yet shows "Coming soon". --}}
<div class="store-row">
    @if($stores['android'])
        <a class="store-btn" href="{{ $stores['android'] }}" target="_blank" rel="noopener"><x-icon name="phone"/><span><small>Android</small><b>Google Play</b></span></a>
    @elseif($stores['apk'])
        <a class="store-btn" href="{{ $stores['apk'] }}"><x-icon name="download"/><span><small>Android</small><b>Download the app</b></span></a>
    @else
        <span class="store-btn soon"><x-icon name="phone"/><span><small>Android</small><b>Coming soon</b></span></span>
    @endif

    @if($stores['ios'])
        <a class="store-btn" href="{{ $stores['ios'] }}" target="_blank" rel="noopener"><x-icon name="phone"/><span><small>iPhone</small><b>App Store</b></span></a>
    @else
        <span class="store-btn soon"><x-icon name="phone"/><span><small>iPhone</small><b>Coming soon</b></span></span>
    @endif
</div>
@if($stores['android'] && $stores['apk'])
    <p class="small" style="margin-top:10px;opacity:.85">Play Store not available on your phone? <a href="{{ $stores['apk'] }}" style="color:inherit;font-weight:700">Download the APK</a>.</p>
@endif
