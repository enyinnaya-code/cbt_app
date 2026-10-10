@extends('layouts.public')

@section('title', 'Forgot my password')
@section('robots', 'noindex, follow')

@section('content')
<div class="auth-card stack" style="gap:24px">
    <div class="col">
        <h1 class="h1">Forgot your password?</h1>
        <p class="muted">Type the email you signed up with. We will send a link to that email where you can choose a new password.</p>
    </div>

    @if(session('status'))<div class="alert ok" role="status">{{ session('status') }}</div>@endif
    @if(session('error'))<div class="alert err" role="alert">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('password.email') }}" class="stack" style="gap:16px">
        @csrf
        <div class="field">
            <label for="email">Email address</label>
            <div class="input-wrap">
                <x-icon name="mail"/>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input @error('email') err @enderror" autocomplete="email" required autofocus>
            </div>
            @error('email')<span class="errtext">{{ $message }}</span>@enderror
        </div>
        <button class="btn btn-p block" type="submit">Send me the link</button>
    </form>

    <p class="small muted center"><a class="link" href="{{ route('login') }}">Back to sign in</a></p>
</div>
@endsection
