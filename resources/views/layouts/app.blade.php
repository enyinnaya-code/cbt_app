@extends('layouts.base')

@php
    $user = auth()->user();
    $isStaff = $user->canManageQuestions();
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');

    // Old school tests waiting to be turned into papers (admins only; hidden once there are none).
    $legacyCount = $isStaff && $user->isAdmin() ? app(\App\Services\LegacyTagger::class)->untaggedCount() : 0;

    // [label, route name, icon, active pattern]
    $nav = $isStaff
        ? [
            ['Overview', 'dashboard', 'grid', 'dashboard'],
            ['Papers', 'console.papers.index', 'layers', 'console.papers.*'],
            ['Topics', 'console.topics.index', 'book', 'console.topics.*'],
            ['Packs', 'console.packs.index', 'download', 'console.packs.*'],
            ...($legacyCount ? [['Old tests', 'console.legacy.index', 'refresh', 'console.legacy.*']] : []),
            ...($user->isAdmin() ? [
                ['Payments', 'console.payments.index', 'card', 'console.payments.*'],
                ['Exams', 'console.exams.index', 'target', 'console.exams.*'],
                ['Content', 'console.posts.index', 'news', ['console.posts.*', 'console.events.*', 'console.settings.*']],
                ['Users', 'console.users.index', 'users', 'console.users.*'],
            ] : []),
        ]
        : [
            ['Home', 'dashboard', 'home', 'dashboard'],
            ['Practice', 'practice.index', 'book', 'practice.*'],
            ['Mock', 'mock.index', 'clock', 'mock.*'],
            ['Progress', 'progress', 'chart', 'progress'],
            ['Saved', 'saved', 'bookmark', 'saved'],
        ];
    // Phones have room for five tabs: keep the ones staff use daily.
    $mobileNav = $isStaff ? array_values(array_filter($nav, fn ($n) => in_array($n[1], ['dashboard', 'console.papers.index', 'console.posts.index', 'console.users.index', 'console.topics.index'], true)))
        : [
        ['Home', 'dashboard', 'home', 'dashboard'],
        ['Practice', 'practice.index', 'book', 'practice.*'],
        ['Mock', 'mock.index', 'clock', 'mock.*'],
        ['Progress', 'progress', 'chart', 'progress'],
        ['Profile', 'profile', 'user', 'profile'],
    ];
@endphp

@section('body')
<header class="topbar">
    <div class="topbar-in">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark"><x-icon name="check" size="s"/></span>TestaCBT
        </a>
        <nav class="topnav" aria-label="Main">
            @foreach($nav as [$label, $routeName, $icon, $pattern])
                @if(Route::has($routeName))
                    <a href="{{ route($routeName) }}" class="{{ request()->routeIs(...(array) $pattern) ? 'on' : '' }}">{{ $label }}</a>
                @endif
            @endforeach
        </nav>
        <div class="topbar-r">
            @if($isStaff)<span class="badge neutral">{{ ucfirst($user->role) }}</span>@endif
            <div class="usermenu">
                <button type="button" class="avatar sm" data-menu-toggle aria-label="Account menu" style="border:0;cursor:pointer">
                    @if($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="">@else{{ $initials }}@endif
                </button>
                <div class="usermenu-panel">
                    <div class="who"><p class="h3">{{ $user->name }}</p><p class="small muted">{{ $user->email }}</p></div>
                    <a href="{{ route('welcome') }}"><x-icon name="globe" size="s"/>Back to the website</a>
                    @if(Route::has('profile'))
                        <a href="{{ route('profile') }}"><x-icon name="user" size="s"/>Profile and settings</a>
                    @endif
                    @unless($isStaff)<a href="{{ route('orders.index') }}"><x-icon name="card" size="s"/>My purchases</a>@endunless
                    <div class="seg" data-theme-group style="margin:6px 0">
                        <button type="button" data-theme-val="light"><x-icon name="sun" size="s"/>&nbsp;Light</button>
                        <button type="button" data-theme-val="dark"><x-icon name="moon" size="s"/>&nbsp;Dark</button>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">@csrf
                        <button type="submit"><x-icon name="logout" size="s"/>Sign out</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</header>

<main class="page @yield('page_class')">
    @if(session('success'))<div class="alert ok" role="status">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert err" role="alert">{{ session('error') }}</div>@endif
    @yield('content')
</main>

<nav class="bottom-nav" aria-label="Main">
    @foreach($mobileNav as [$label, $routeName, $icon, $pattern])
        @if(Route::has($routeName))
            <a href="{{ route($routeName) }}" class="{{ request()->routeIs(...(array) $pattern) ? 'on' : '' }}">
                <span class="pill"><x-icon name="{{ $icon }}"/></span>{{ $label }}
            </a>
        @endif
    @endforeach
</nav>
@endsection
