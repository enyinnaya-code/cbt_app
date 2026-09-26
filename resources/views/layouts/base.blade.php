<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'TestaCBT') | TestaCBT</title>
    <link rel="icon" type="image/png" href="{{ asset('images/testa_logo_lg.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,800&family=Figtree:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/testacbt-tokens.css') }}">
    <link rel="stylesheet" href="{{ asset('css/testacbt.css') }}">
    {{-- Apply a saved light/dark choice before first paint so the page never flashes the wrong theme. --}}
    <script>try{var t=localStorage.getItem('tc-theme');if(t==='light'||t==='dark'){document.documentElement.setAttribute('data-theme',t)}}catch(e){}</script>
    @stack('head')
</head>
<body>
    @yield('body')
    <script src="{{ asset('js/testacbt.js') }}"></script>
    @stack('scripts')
</body>
</html>
