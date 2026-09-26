@extends('layouts.app')

@section('title', 'Profile')
@section('page_class', 'narrow')

@php
    $initials = collect(preg_split('/\s+/', trim($user->name)))->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    $mine = old('preferred_exams', $user->preferred_exams ?? []);
@endphp

@section('content')
<h1 class="h1">Profile</h1>

<div class="row">
    <div class="avatar" style="width:60px;height:60px;font-size:20px">@if($user->avatar_url)<img src="{{ $user->avatar_url }}" alt="">@else{{ $initials }}@endif</div>
    <div class="grow">
        <p class="h2">{{ $user->name }}</p>
        <p class="small muted">{{ $user->email }}</p>
        @if($user->google_id)<span class="badge n" style="margin-top:6px">Signed in with Google</span>@endif
    </div>
</div>

<form method="POST" action="{{ route('profile.update') }}" class="stack" style="gap:20px">
    @csrf @method('PUT')

    <div class="field">
        <label for="name">Full name</label>
        <input id="name" class="input @error('name') err @enderror" name="name" value="{{ old('name', $user->name) }}" required maxlength="120">
        @error('name')<span class="errtext">{{ $message }}</span>@enderror
    </div>

    <div class="field">
        <span class="label">Which exams are you writing?</span>
        <div class="chips">
            @foreach($exams as $e)
                <label class="chip"><input type="checkbox" name="preferred_exams[]" value="{{ $e->slug }}" @checked(in_array($e->slug, $mine))>{{ $e->name }}</label>
            @endforeach
        </div>
    </div>

    <div class="grid2">
        <div class="field">
            <label for="target_exam">Exam countdown</label>
            <select id="target_exam" class="input" name="target_exam">
                <option value="">None</option>
                @foreach($exams as $e)<option value="{{ $e->slug }}" @selected(old('target_exam', $user->target_exam) === $e->slug)>{{ $e->name }}</option>@endforeach
            </select>
        </div>
        <div class="field">
            <label for="target_exam_date">Exam date</label>
            <input id="target_exam_date" type="date" class="input @error('target_exam_date') err @enderror" name="target_exam_date"
                   value="{{ old('target_exam_date', $user->target_exam_date?->toDateString()) }}" min="{{ now()->toDateString() }}">
            @error('target_exam_date')<span class="errtext">{{ $message }}</span>@enderror
        </div>
    </div>

    <div class="field">
        <span class="label">Explanation language</span>
        <div class="seg">
            <label><input type="radio" name="explanation_language" value="en" @checked(old('explanation_language', $user->explanation_language) === 'en')>English</label>
            <label><input type="radio" name="explanation_language" value="pcm" @checked(old('explanation_language', $user->explanation_language) === 'pcm')>Pidgin</label>
        </div>
        <span class="hint">Used when a question has both. You can switch on any question.</span>
    </div>

    <div><button class="btn btn-p" type="submit">Save settings</button></div>
</form>

<div class="field">
    <span class="label">Appearance</span>
    <div class="seg" data-theme-group>
        <button type="button" data-theme-val="system">System</button>
        <button type="button" data-theme-val="light">Light</button>
        <button type="button" data-theme-val="dark">Dark</button>
    </div>
</div>

<section class="card stack" style="gap:16px">
    <h2 class="h2">{{ $user->password ? 'Change password' : 'Set a password' }}</h2>
    @if(! $user->password)<p class="small muted">You signed in with Google. Set a password if you also want to sign in with your email.</p>@endif
    <form method="POST" action="{{ route('profile.password') }}" class="stack" style="gap:14px">
        @csrf @method('PUT')
        @if($user->password)
        <div class="field">
            <label for="current_password">Current password</label>
            <input id="current_password" type="password" class="input @if($errors->password->has('current_password')) err @endif" name="current_password" autocomplete="current-password" required>
            @if($errors->password->has('current_password'))<span class="errtext">{{ $errors->password->first('current_password') }}</span>@endif
        </div>
        @endif
        <div class="field">
            <label for="new_password">New password</label>
            <input id="new_password" type="password" class="input @if($errors->password->has('password')) err @endif" name="password" autocomplete="new-password" minlength="8" required>
            @if($errors->password->has('password'))<span class="errtext">{{ $errors->password->first('password') }}</span>@endif
        </div>
        <div class="field">
            <label for="new_password_confirmation">Confirm new password</label>
            <input id="new_password_confirmation" type="password" class="input" name="password_confirmation" autocomplete="new-password" required>
        </div>
        <div><button class="btn btn-o" type="submit">Update password</button></div>
    </form>
</section>

<form method="POST" action="{{ route('logout') }}">@csrf
    <button class="btn btn-d" type="submit"><x-icon name="logout" size="s"/>Sign out</button>
</form>
@endsection
