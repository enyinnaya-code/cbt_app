@extends('layouts.base')

@section('body')
<div class="auth-wrap">
    <header class="pub-top">
        <a class="brand" href="{{ route('welcome') }}"><span class="brand-mark"><x-icon name="check" size="s"/></span>TestaCBT</a>
        <div class="row">
            <div class="seg" data-theme-group style="min-width:160px">
                <button type="button" data-theme-val="light" aria-label="Light theme"><x-icon name="sun" size="s"/></button>
                <button type="button" data-theme-val="dark" aria-label="Dark theme"><x-icon name="moon" size="s"/></button>
            </div>
            @yield('top_actions')
        </div>
    </header>

    <div style="flex:1">@yield('content')</div>

    <footer class="footer">&copy; {{ date('Y') }} TestaCBT. Practise WAEC, NECO and JAMB past questions.</footer>
</div>
@endsection
