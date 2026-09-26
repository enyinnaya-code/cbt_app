<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Admin only. */
class UserController extends Controller
{
    /** Legacy user_type each role maps to (the old school screens still read it). */
    private const TYPE = [User::ROLE_ADMIN => 2, User::ROLE_EXAMINER => 3, User::ROLE_STUDENT => 4];

    public function index(Request $request)
    {
        $f = $request->validate(['q' => ['nullable', 'string', 'max:80'], 'role' => ['nullable', 'in:admin,examiner,student'], 'state' => ['nullable', 'in:active,suspended']]);

        $users = User::query()
            ->when($f['q'] ?? null, fn ($q, $v) => $q->where(fn ($w) => $w->where('name', 'like', '%' . addcslashes($v, '%_\\') . '%')->orWhere('email', 'like', '%' . addcslashes($v, '%_\\') . '%')))
            ->when($f['role'] ?? null, fn ($q, $v) => $q->where('role', $v))
            ->when(($f['state'] ?? null) === 'active', fn ($q) => $q->where('is_active', 1))
            ->when(($f['state'] ?? null) === 'suspended', fn ($q) => $q->where('is_active', 0))
            ->orderByRaw("CASE role WHEN 'admin' THEN 0 WHEN 'examiner' THEN 1 ELSE 2 END")->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('console.users', [
            'users' => $users,
            'filters' => $f,
            'counts' => User::selectRaw('role, COUNT(*) as n')->groupBy('role')->pluck('n', 'role'),
        ]);
    }

    /** Examiners and admins are appointed here. Students sign themselves up. */
    public function store(Request $request)
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);   // uniqueness must not depend on letter case

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'role' => ['required', Rule::in([User::ROLE_EXAMINER, User::ROLE_ADMIN])],
            'password' => ['required', 'string', 'min:8', 'max:200'],
        ]);

        User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'user_type' => self::TYPE[$data['role']],
            'added_by' => $request->user()->id,
        ]);

        return back()->with('success', "Created the {$data['role']} account for {$data['email']}. Share the password with them privately and ask them to change it.");
    }

    public function role(Request $request, User $user)
    {
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(self::TYPE))]]);

        if ($refusal = $this->refuseSelfOrLastAdmin($request, $user, $data['role'] !== User::ROLE_ADMIN)) {
            return back()->with('error', $refusal);
        }

        // Keep the existing user_type when the role is unchanged (a super admin stays a super admin).
        if ($user->role !== $data['role']) {
            $user->update(['user_type' => self::TYPE[$data['role']]]);
            $user->tokens()->delete();   // sign the phone app out so the new permissions apply cleanly
        }

        $now = ['admin' => 'an admin', 'examiner' => 'an examiner', 'student' => 'a student'][$data['role']];

        return back()->with('success', "{$user->name} is now {$now}.");
    }

    public function toggle(Request $request, User $user)
    {
        $suspending = (int) $user->is_active === 1;

        if ($suspending && ($refusal = $this->refuseSelfOrLastAdmin($request, $user, true))) {
            return back()->with('error', $refusal);
        }

        $user->update(['is_active' => $suspending ? 0 : 1, 'login_attempts' => 3]);
        if ($suspending) { $user->tokens()->delete(); }

        return back()->with('success', $suspending ? "{$user->name} is suspended." : "{$user->name} can sign in again.");
    }

    /** Stops an admin locking themselves, or everyone, out. */
    private function refuseSelfOrLastAdmin(Request $request, User $target, bool $wouldRemoveAdmin): ?string
    {
        if (! $wouldRemoveAdmin || ! $target->isAdmin()) { return null; }

        if ($target->id === $request->user()->id) {
            return 'You cannot remove your own admin access or suspend yourself. Ask another admin to do it.';
        }
        if (User::where('role', User::ROLE_ADMIN)->where('is_active', 1)->where('id', '!=', $target->id)->doesntExist()) {
            return 'That is the last active admin. Make someone else an admin first.';
        }

        return null;
    }
}
