@extends('layouts.app')

@section('title', $editing ? 'Your ranking profile' : 'Join the daily challenge')
@section('page_class', 'mid')

@section('content')
<div class="col">
    <h1 class="h1">{{ $editing ? 'Your ranking profile' : 'Join the daily challenge' }}</h1>
    <p class="muted">{{ $editing ? 'This is what other students see on the rankings.' : 'A 10-question challenge every day. Your results are ranked against other students, shown by a nickname, not your name.' }}</p>
</div>

@if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

<form method="POST" action="{{ route('rankings.join.store') }}" class="card stack" style="gap:18px">
    @csrf

    <div class="field">
        <label for="nickname">Nickname</label>
        <input id="nickname" name="nickname" class="input" maxlength="20" minlength="3" required autocomplete="off" value="{{ old('nickname', $profile['nickname']) }}" placeholder="For example: SharpOwl22">
        <p class="small muted">3 to 20 letters or numbers. Everyone on the rankings can see it, so do not use your full name.</p>
    </div>

    <div class="field">
        <span class="label" id="avatar-label">Pick a picture</span>
        <div class="row wrap" role="radiogroup" aria-labelledby="avatar-label" style="gap:10px">
            @foreach($profile['avatars'] as $a)
                <label style="cursor:pointer">
                    <input type="radio" name="avatar" value="{{ $a['key'] }}" required class="sr-only" @checked(old('avatar', $profile['avatar']) === $a['key'])>
                    <span class="avatar-choice" style="display:grid;place-items:center;width:52px;height:52px;border-radius:16px;font-size:26px;border:2px solid var(--border);background:var(--surface)">{{ $a['emoji'] }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div class="field">
        <label for="school">School <span class="muted">(optional)</span></label>
        <input id="school" name="school" class="input" maxlength="80" autocomplete="off" value="{{ old('school', $profile['school']) }}" placeholder="Add it to help your school climb the school board">
        <p class="small muted">If you add it, other students can see which school you named, and your points count toward it. Leave it empty to stay on your own.</p>
    </div>

    <label class="row" style="gap:10px;align-items:flex-start">
        <input type="checkbox" name="accept_privacy" value="1" required style="margin-top:4px" @checked(old('accept_privacy'))>
        <span class="small">I have read and accept the <a class="link" href="{{ route('privacy') }}" target="_blank" rel="noopener">privacy policy</a>. I understand that my nickname, picture, points and school (if I add one) are shown to other students on the rankings, and that I can leave at any time.</span>
    </label>

    <div class="row wrap" style="gap:10px">
        <button class="btn btn-p" type="submit">{{ $editing ? 'Save changes' : 'Join and start' }}</button>
        <a class="btn btn-o" href="{{ route('dashboard') }}">Not now</a>
    </div>
</form>

@if($editing)
<form method="POST" action="{{ route('rankings.leave') }}" class="card stack" style="gap:8px">
    @csrf
    <p class="h3">Leave the rankings</p>
    <p class="small muted">Your nickname, picture and school are deleted and you disappear from every board. Your practice history stays.</p>
    <button class="btn btn-d btn-sm" type="submit" style="align-self:flex-start" data-confirm="Leave the rankings? Your nickname, picture and school will be deleted.">Leave the rankings</button>
</form>
@endif

<style>.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}input:checked+.avatar-choice{border-color:var(--primary);background:var(--primary-soft)}input:focus-visible+.avatar-choice{outline:3px solid var(--accent);outline-offset:2px}</style>
@endsection
