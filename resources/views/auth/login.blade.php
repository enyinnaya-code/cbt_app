@extends('layouts.public')

@section('title', 'Sign in')

@section('content')
<div class="auth-card stack" style="gap:24px">
    <div class="col">
        <h1 class="h1">Welcome back</h1>
        <p class="muted">Sign in to pick up where you stopped.</p>
    </div>

    @if(session('error'))<div class="alert err" role="alert">{{ session('error') }}</div>@endif
    @if(session('success'))<div class="alert ok" role="status">{{ session('success') }}</div>@endif

    <form method="POST" action="{{ route('login.submit') }}" class="stack" style="gap:16px">
        @csrf
        <div class="field">
            <label for="email">Email address</label>
            <div class="input-wrap">
                <x-icon name="mail"/>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input @error('email') err @enderror" autocomplete="email" required autofocus>
            </div>
            @error('email')<span class="errtext">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
                <x-icon name="lock"/>
                <input id="password" type="password" name="password" class="input @error('password') err @enderror" autocomplete="current-password" required>
            </div>
            @error('password')<span class="errtext">{{ $message }}</span>@enderror
        </div>
        <label class="row small" style="gap:8px"><input type="checkbox" name="remember" value="1"> Keep me signed in on this device</label>
        <button class="btn btn-p block" type="submit">Sign in</button>
    </form>

    <p class="small muted center">New to TestaCBT? <a class="link" href="{{ route('register') }}">Create account</a></p>
</div>
@endsection
