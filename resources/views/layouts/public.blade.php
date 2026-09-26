@extends('layouts.base')

@php
    $links = [
        ['News', route('news'), 'news'],
        ['Scholarships', route('scholarships'), 'scholarships'],
        ['Events', route('events'), 'events'],
        ['Blog', route('blog'), 'blog'],
        ['Pricing', route('pricing'), 'pricing'],
    ];
@endphp

@section('body')
<div class="auth-wrap">
    <header class="pub-top">
        <a class="brand" href="{{ route('welcome') }}"><span class="brand-mark"><x-icon name="check" size="s"/></span>TestaCBT</a>
        <nav class="pub-links" aria-label="Site">
            @foreach($links as [$label, $url, $route])
                <a href="{{ $url }}" class="{{ request()->routeIs($route) || request()->routeIs($route . '.*') ? 'on' : '' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <div class="row">
            <div class="seg" data-theme-group style="min-width:110px">
                <button type="button" data-theme-val="light" aria-label="Light theme"><x-icon name="sun" size="s"/></button>
                <button type="button" data-theme-val="dark" aria-label="Dark theme"><x-icon name="moon" size="s"/></button>
            </div>
            @hasSection('top_actions')
                @yield('top_actions')
            @elseif(auth()->check())
                <a class="btn btn-p btn-sm" href="{{ route('dashboard') }}">Open my account</a>
            @else
                <a class="btn btn-o btn-sm" href="{{ route('login') }}">Sign in</a>
            @endif
        </div>
    </header>

    <div style="flex:1">@yield('content')</div>

    <footer class="footer">
        <div class="footer-cols">
            <div>
                <a class="brand" href="{{ route('welcome') }}" style="padding:0"><span class="brand-mark"><x-icon name="check" size="s"/></span>TestaCBT</a>
                <p style="margin-top:10px;max-width:34ch">Practise WAEC, NECO, JAMB, Post-UTME and IGCSE past questions, on the web or on your phone.</p>
            </div>
            <div><h4>Learn</h4><a href="{{ route('register') }}">Create a free account</a><a href="{{ route('pricing') }}">Pricing</a><a href="{{ route('download') }}">Get the app</a></div>
            <div><h4>Stay informed</h4><a href="{{ route('news') }}">News</a><a href="{{ route('scholarships') }}">Scholarships</a><a href="{{ route('events') }}">Events</a><a href="{{ route('blog') }}">Blog</a></div>
            <div><h4>Account</h4><a href="{{ route('login') }}">Sign in</a><a href="{{ route('register') }}">Sign up</a>@if($support = \App\Models\Setting::get('support.email'))<a href="mailto:{{ $support }}">{{ $support }}</a>@endif</div>
        </div>
        &copy; {{ date('Y') }} TestaCBT
    </footer>
</div>
@endsection
