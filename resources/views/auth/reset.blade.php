@extends('layouts.public')

@section('title', 'Choose a new password')
@section('robots', 'noindex, nofollow')

@section('content')
<div class="auth-card stack" style="gap:24px">
    <div class="col">
        <h1 class="h1">Choose a new password</h1>
        <p class="muted">At least 8 characters, with letters and numbers.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="stack" style="gap:16px">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="field">
            <label for="email">Email address</label>
            <div class="input-wrap">
                <x-icon name="mail"/>
                <input id="email" type="email" name="email" value="{{ old('email', $email) }}" class="input @error('email') err @enderror" autocomplete="email" required>
            </div>
            @error('email')<span class="errtext">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="password">New password</label>
            <div class="input-wrap">
                <x-icon name="lock"/>
                <input id="password" type="password" name="password" class="input @error('password') err @enderror" autocomplete="new-password" required autofocus>
            </div>
            @error('password')<span class="errtext">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Type it again</label>
            <div class="input-wrap">
                <x-icon name="lock"/>
                <input id="password_confirmation" type="password" name="password_confirmation" class="input" autocomplete="new-password" required>
            </div>
        </div>
        <button class="btn btn-p block" type="submit">Change password</button>
    </form>

    <p class="small muted center"><a class="link" href="{{ route('password.request') }}">Send me a new link</a> &middot; <a class="link" href="{{ route('login') }}">Back to sign in</a></p>
</div>
@endsection
