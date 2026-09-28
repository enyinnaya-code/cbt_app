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

@php
    $keep = array_filter(['q' => $filters['q'] ?? null, 'state' => $filters['state'] ?? null, 'per_page' => $perPage !== 25 ? $perPage : null]);
    $tabUrl = fn (string $role) => route('console.users.index', ['tab' => $role] + $keep);
    $meta = $tabs[$tab];
@endphp

<nav class="tabbar" aria-label="Kinds of user">
    @foreach($tabs as $role => $t)
        <a href="{{ $tabUrl($role) }}" class="{{ $role === $tab ? 'on' : '' }}" @if($role === $tab) aria-current="page" @endif>{{ $t['title'] }} <span class="badge {{ $role === $tab ? 'g' : 'neutral' }}">{{ number_format($matches[$role]) }}</span></a>
    @endforeach
</nav>

<form method="GET" class="filters card" style="padding:14px">
    <input type="hidden" name="tab" value="{{ $tab }}">
    @if($perPage !== 25)<input type="hidden" name="per_page" value="{{ $perPage }}">@endif
    <div class="field"><label for="uq">Search</label><input id="uq" name="q" class="input" value="{{ $filters['q'] ?? '' }}" placeholder="Name or email"></div>
    <div class="field" style="max-width:170px"><label for="us">Status</label><select id="us" name="state" class="input"><option value="">All</option><option value="active" @selected(($filters['state'] ?? null) === 'active')>Active</option><option value="suspended" @selected(($filters['state'] ?? null) === 'suspended')>Suspended</option></select></div>
    <div class="row"><button class="btn btn-o btn-sm" type="submit">Filter</button>@if(! empty($filters['q']) || ! empty($filters['state']))<a class="btn btn-t btn-sm" href="{{ route('console.users.index', ['tab' => $tab]) }}">Clear</a>@endif</div>
</form>

<section class="stack" style="gap:10px" aria-labelledby="users-{{ $tab }}">
    <div class="row between">
        <h2 class="h2" id="users-{{ $tab }}">{{ $meta['title'] }} <span class="badge neutral">{{ number_format($users->total()) }}</span></h2>
        @if($users->total() > 0)<span class="small muted">Showing {{ number_format($users->firstItem()) }} to {{ number_format($users->lastItem()) }} of {{ number_format($users->total()) }}</span>@endif
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th style="width:56px">S/N</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td class="muted">{{ $users->firstItem() + $loop->index }}</td>
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
                <tr><td colspan="7"><div class="empty">
                    @if(! empty($filters['q']) || ! empty($filters['state']))
                        <p class="h3">No {{ strtolower($meta['title']) }} match</p><p class="small muted">Try a different name or clear the filters.</p>
                    @else
                        <p class="h3">{{ $meta['empty'] }}</p>
                    @endif
                </div></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($users->total() > 0)
    <div class="pager-bar">
        <form method="GET" class="row" style="gap:8px">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @foreach(array_diff_key($keep, ['per_page' => 1]) as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <label class="small muted" for="upp">Per page</label>
            <select id="upp" name="per_page" class="input" style="min-height:36px;width:auto;padding:0 10px" onchange="this.form.requestSubmit()">
                @foreach(\App\Http\Controllers\Console\UserController::PER_PAGE as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
            </select>
        </form>
        {{ $users->links('vendor.pagination.testacbt') }}
        @if($users->lastPage() > 7)
        <form method="GET" class="row" style="gap:8px">
            <input type="hidden" name="tab" value="{{ $tab }}">
            @foreach($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <label class="small muted" for="upage">Go to page</label>
            <input id="upage" name="page" type="number" min="1" max="{{ $users->lastPage() }}" class="input" style="min-height:36px;width:84px;padding:0 10px" placeholder="1 to {{ $users->lastPage() }}">
            <button class="btn btn-o btn-sm" type="submit">Go</button>
        </form>
        @endif
    </div>
    @endif
</section>
@endsection
