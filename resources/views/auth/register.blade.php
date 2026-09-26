@extends('layouts.public')

@section('title', 'Create account')
@section('robots', 'noindex, follow')

@section('content')
<div class="auth-card stack" style="gap:24px">
    <div class="col">
        <h1 class="h1">Create your account</h1>
        <p class="muted">Takes less than a minute.</p>
    </div>

    <form method="POST" action="{{ route('register.submit') }}" class="stack" style="gap:16px">
        @csrf
        <div class="field">
            <label for="name">Full name</label>
            <div class="input-wrap">
                <x-icon name="user"/>
                <input id="name" type="text" name="name" value="{{ old('name') }}" class="input @error('name') err @enderror" autocomplete="name" required autofocus>
            </div>
            @error('name')<span class="errtext">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="email">Email address</label>
            <div class="input-wrap">
                <x-icon name="mail"/>
                <input id="email" type="email" name="email" value="{{ old('email') }}" class="input @error('email') err @enderror" autocomplete="email" required>
            </div>
            @error('email')<span class="errtext">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="password">Password</label>
            <div class="input-wrap">
                <x-icon name="lock"/>
                <input id="password" type="password" name="password" class="input @error('password') err @enderror" autocomplete="new-password" required minlength="8">
            </div>
            <span class="hint">At least 8 characters.</span>
            @error('password')<span class="errtext">{{ $message }}</span>@enderror
        </div>
        <div class="field">
            <label for="password_confirmation">Confirm password</label>
            <div class="input-wrap">
                <x-icon name="lock"/>
                <input id="password_confirmation" type="password" name="password_confirmation" class="input" autocomplete="new-password" required>
            </div>
        </div>
        <div class="field">
            <span class="label">Which exams are you writing?</span>
            <div class="chips">
                @foreach($exams as $exam)
                    <label class="chip"><input type="checkbox" name="preferred_exams[]" value="{{ $exam->slug }}" @checked(in_array($exam->slug, old('preferred_exams', [])))>{{ $exam->name }}</label>
                @endforeach
            </div>
        </div>
        <button class="btn btn-p block" type="submit">Create account</button>
    </form>

    <p class="small muted center">Already have an account? <a class="link" href="{{ route('login') }}">Sign in</a></p>
</div>
@endsection
