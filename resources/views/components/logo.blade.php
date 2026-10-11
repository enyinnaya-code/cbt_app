@props(['size' => 34])
{{-- The TestaCBT logo (the same artwork as the app icon and favicon): a green rounded square with a ring and a check. Corners are transparent. --}}
<svg class="logo" width="{{ $size }}" height="{{ $size }}" viewBox="0 0 100 100" role="img" aria-label="TestaCBT" style="display:block;flex:none">
    <rect width="100" height="100" rx="22" fill="#0A7A4A"/>
    <circle cx="50" cy="50" r="30" fill="none" stroke="#fff" stroke-width="9"/>
    <path d="M35.5 52l10.5 10.5 20-23.5" fill="none" stroke="#fff" stroke-width="11" stroke-linecap="round" stroke-linejoin="round"/>
</svg>
