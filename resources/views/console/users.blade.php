@extends('layouts.app')

@section('title', 'Users')

@php $me = auth()->id(); @endphp

@section('content')
<div class="col"><h1 class="h1">Users</h1>
    <p class="muted">{{ number_format($counts['student'] ?? 0) }} students &middot; {{ $counts['examiner'] ?? 0 }} examiners &middot; {{ $counts['admin'] ?? 0 }} admins</p></div>

@if($errors->any())<div class="alert err">{{ $errors->first() }}</div>@endif

<details class="card" {{ $errors->any() ? 'open' : '' }}>
    <summary class="h3" style="cursor:pointer">Add an examiner or admin</summary>
    <form method="POST" action="{{ route('console.users.store') }}" class="stack" style="gap:14px;margin-top:16px">
        @csrf
        <div class="grid2">
            <div class="field"><label for="u-name">Full name</label><input id="u-name" name="name" class="input" value="{{ old('name') }}" required maxlength="120"></div>
            <div class="field"><label for="u-email">Email</label><input id="u-email" name="email" type="email" class="input" value="{{ old('email') }}" required></div>
            <div class="field"><label for="u-role">Role</label><select id="u-role" name="role" class="input"><option value="examiner">Examiner (adds and edits questions)</option><option value="admin">Admin (also publishes and manages users)</option></select></div>
            <div class="field"><label for="u-pass">Temporary password</label><input id="u-pass" name="password" type="text" class="input" minlength="8" required autocomplete="off"><span class="hint">At least 8 characters. Send it to them privately.</span></div>
        </div>
        <div><button class="btn btn-p btn-sm" type="submit">Create account</button></div>
    </form>
</details>

<form method="GET" class="filters card" style="padding:14px">
    <div class="field"><label for="uq">Search</label><input id="uq" name="q" class="input" value="{{ $filters['q'] ?? '' }}" placeholder="Name or email"></div>
    <div class="field" style="max-width:170px"><label for="ur">Role</label><select id="ur" name="role" class="input"><option value="">All</option>@foreach(['admin', 'examiner', 'student'] as $r)<option value="{{ $r }}" @selected(($filters['role'] ?? null) === $r)>{{ ucfirst($r) }}</option>@endforeach</select></div>
    <div class="field" style="max-width:170px"><label for="us">Status</label><select id="us" name="state" class="input"><option value="">All</option><option value="active" @selected(($filters['state'] ?? null) === 'active')>Active</option><option value="suspended" @selected(($filters['state'] ?? null) === 'suspended')>Suspended</option></select></div>
    <div class="row"><button class="btn btn-o btn-sm" type="submit">Filter</button>@if(array_filter($filters))<a class="btn btn-t btn-sm" href="{{ route('console.users.index') }}">Clear</a>@endif</div>
</form>

<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
        <tbody>
        @forelse($users as $u)
            <tr>
                <td>{{ $u->name }}@if($u->id === $me) <span class="badge neutral">you</span>@endif</td>
                <td class="small">{{ $u->email }}@if($u->google_id) <span class="badge neutral">Google</span>@endif</td>
                <td>
                    @if($u->id === $me)
                        <span class="badge g">{{ ucfirst($u->role) }}</span>
                    @else
                        <form method="POST" action="{{ route('console.users.role', $u) }}" class="row" style="gap:6px">@csrf @method('PATCH')
                            <select name="role" class="input" style="min-height:36px;width:auto;padding:0 10px" aria-label="Role for {{ $u->name }}" onchange="this.form.requestSubmit()">
                                @foreach(['student', 'examiner', 'admin'] as $r)<option value="{{ $r }}" @selected($u->role === $r)>{{ ucfirst($r) }}</option>@endforeach
                            </select>
                        </form>
                    @endif
                </td>
                <td><span class="badge {{ $u->is_active ? 'g' : 'r' }}">{{ $u->is_active ? 'Active' : 'Suspended' }}</span></td>
                <td class="small muted">{{ $u->created_at?->format('j M Y') }}</td>
                <td><div class="actions">@if($u->id !== $me)
                    <form method="POST" action="{{ route('console.users.toggle', $u) }}">@csrf @method('PATCH')
                        <button class="btn {{ $u->is_active ? 'btn-d' : 'btn-o' }} btn-sm" type="submit" @if($u->is_active) data-confirm="Suspend {{ $u->name }}? They will be signed out and unable to sign in." @endif>{{ $u->is_active ? 'Suspend' : 'Reactivate' }}</button></form>@endif</div></td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty"><p class="h3">No users match</p></div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>

{{ $users->links('vendor.pagination.testacbt') }}
@endsection
