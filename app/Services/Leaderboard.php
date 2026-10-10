<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The daily challenge is optional, and so are the rankings it feeds. A student opts in by choosing a nickname and a
 * picture (and, if they like, a school) and accepting the privacy policy. From then on they appear on the boards under
 * those, never under their real name or email. Leaving clears all of it.
 */
class Leaderboard
{
    public const SCOPES = ['week', 'all', 'schools'];

    // ---------------------------------------------------------------- joining and leaving

    /** Opted in, with a nickname, and has accepted the current version of the policy. */
    public static function isParticipant(?User $user): bool
    {
        return $user !== null
            && (bool) $user->leaderboard_opt_in
            && $user->nickname !== null && $user->nickname !== ''
            && $user->privacy_version === config('testacbt.privacy_version');
    }

    public static function nicknameKey(string $nickname): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($nickname)));
    }

    public static function cleanSchool(?string $school): ?string
    {
        $school = trim(preg_replace('/\s+/u', ' ', (string) $school));

        return $school === '' ? null : $school;
    }

    /** @return array<string, array<int, mixed>> */
    public static function rules(User $user): array
    {
        $reserved = config('testacbt.reserved_nicknames');

        return [
            'nickname' => ['required', 'string', 'min:3', 'max:20', 'regex:/^[\p{L}\p{N}][\p{L}\p{N}_.\- ]*$/u',
                function ($attribute, $value, $fail) use ($user, $reserved) {
                    $key = self::nicknameKey((string) $value);
                    $squashed = preg_replace('/[^a-z0-9]/', '', Str::ascii($key));
                    foreach ($reserved as $word) {
                        if (str_contains($squashed, $word)) { return $fail('Please choose a different nickname.'); }
                    }
                    if (User::where('nickname_key', $key)->where('id', '!=', $user->id)->exists()) {
                        return $fail('Someone already uses that nickname. Try another.');
                    }
                },
            ],
            'avatar' => ['required', Rule::in(array_keys(config('testacbt.avatars')))],
            'school' => ['nullable', 'string', 'max:80', 'regex:/^[\p{L}\p{N}][\p{L}\p{N}\'.,&()\- ]*$/u',
                function ($attribute, $value, $fail) use ($reserved) {
                    $squashed = preg_replace('/[^a-z0-9]/', '', Str::ascii(mb_strtolower((string) $value)));
                    foreach ($reserved as $word) {
                        if (str_contains($squashed, $word)) { return $fail('Please check the school name.'); }
                    }
                },
            ],
            'accept_privacy' => ['accepted'],
        ];
    }

    public static function messages(): array
    {
        return [
            'nickname.regex' => 'Use letters, numbers, spaces, dots, dashes or underscores, starting with a letter or number.',
            'school.regex' => 'Please check the school name: letters, numbers and simple punctuation only.',
            'accept_privacy.accepted' => 'Please read and accept the privacy policy to join the rankings.',
        ];
    }

    /** @param array{nickname:string,avatar:string,school?:?string} $data */
    public static function join(User $user, array $data): void
    {
        $school = self::cleanSchool($data['school'] ?? null);

        $user->forceFill([
            'leaderboard_opt_in' => true,
            'nickname' => trim(preg_replace('/\s+/u', ' ', $data['nickname'])),
            'nickname_key' => self::nicknameKey($data['nickname']),
            'avatar' => $data['avatar'],
            'school' => $school,
            'school_key' => $school ? mb_strtolower($school) : null,
            'privacy_accepted_at' => now(),
            'privacy_version' => config('testacbt.privacy_version'),
        ])->save();
    }

    /** Takes the student off every board at once and forgets what they chose. Their practice history is untouched. */
    public static function leave(User $user): void
    {
        $user->forceFill([
            'leaderboard_opt_in' => false, 'nickname' => null, 'nickname_key' => null, 'avatar' => null,
            'school' => null, 'school_key' => null, 'privacy_accepted_at' => null, 'privacy_version' => null,
        ])->save();
    }

    /** What the join form and the app need. */
    public static function profile(User $user): array
    {
        return [
            'opted_in' => self::isParticipant($user),
            'nickname' => $user->nickname,
            'avatar' => $user->avatar,
            'school' => $user->school,
            'avatars' => collect(config('testacbt.avatars'))->map(fn ($emoji, $key) => ['key' => $key, 'emoji' => $emoji])->values()->all(),
            'privacy_version' => config('testacbt.privacy_version'),
            'privacy_url' => url('/privacy'),
        ];
    }

    // ---------------------------------------------------------------- the boards

    /** Points for one completed challenge: 10 for every right answer, plus the completion bonus. */
    private const POINTS = 'COALESCE(c.correct, 0) * 10 + c.xp_awarded';

    private function base(?Carbon $from)
    {
        return DB::table('daily_challenges as c')
            ->join('users as u', 'u.id', '=', 'c.user_id')
            ->where('u.leaderboard_opt_in', 1)->whereNotNull('u.nickname')->where('u.privacy_version', config('testacbt.privacy_version'))
            ->whereNotNull('c.completed_at')
            ->when($from, fn ($q) => $q->where('c.challenge_date', '>=', $from->toDateString()));
    }

    private function from(string $scope): ?Carbon
    {
        return $scope === 'all' ? null : Carbon::today()->startOfWeek();
    }

    /**
     * @return array{scope:string,period:string,rows:array<int,array<string,mixed>>,me:?array<string,mixed>}
     */
    public function board(string $scope, ?User $me = null): array
    {
        $scope = in_array($scope, self::SCOPES, true) ? $scope : 'week';

        return $scope === 'schools' ? $this->schools($me) : $this->people($scope, $me);
    }

    private function people(string $scope, ?User $me): array
    {
        $from = $this->from($scope);
        $limit = (int) config('testacbt.leaderboard_size');

        $rows = $this->base($from)
            ->groupBy('u.id', 'u.nickname', 'u.avatar', 'u.school')
            ->selectRaw('u.id, u.nickname, u.avatar, u.school, SUM(' . self::POINTS . ') as points, SUM(COALESCE(c.correct, 0)) as correct, COUNT(*) as played, MIN(c.completed_at) as first_done')
            ->orderByDesc('points')->orderByDesc('correct')->orderBy('first_done')->limit($limit)->get();

        $emoji = config('testacbt.avatars');
        $rank = 0;
        $prev = null;
        $out = [];
        foreach ($rows as $i => $r) {
            if ($prev === null || (int) $r->points !== $prev) { $rank = $i + 1; }
            $prev = (int) $r->points;
            $out[] = [
                'rank' => $rank, 'nickname' => $r->nickname, 'avatar' => $r->avatar, 'emoji' => $emoji[$r->avatar] ?? '', 'school' => $r->school,
                'points' => (int) $r->points, 'played' => (int) $r->played, 'me' => $me && (int) $r->id === $me->id,
            ];
        }

        return ['scope' => $scope, 'period' => $scope === 'all' ? 'All time' : 'This week', 'rows' => $out, 'me' => $this->myPlace($scope, $from, $me, $out)];
    }

    /** Where the signed-in student stands, even when they are outside the top list. */
    private function myPlace(string $scope, ?Carbon $from, ?User $me, array $listed): ?array
    {
        if (! self::isParticipant($me)) { return null; }

        foreach ($listed as $row) {
            if ($row['me']) { return ['rank' => $row['rank'], 'points' => $row['points'], 'played' => $row['played']]; }
        }

        $mine = $this->base($from)->where('u.id', $me->id)
            ->selectRaw('SUM(' . self::POINTS . ') as points, COUNT(*) as played')->first();
        if (! $mine || ! (int) $mine->played) { return ['rank' => null, 'points' => 0, 'played' => 0]; }

        $ahead = DB::query()->fromSub(
            $this->base($from)->groupBy('u.id')->selectRaw('u.id, SUM(' . self::POINTS . ') as points'), 't'
        )->where('t.points', '>', (int) $mine->points)->count();

        return ['rank' => $ahead + 1, 'points' => (int) $mine->points, 'played' => (int) $mine->played];
    }

    /** Schools rank by the average points of the students who played, so a big school has no built-in lead. */
    private function schools(?User $me): array
    {
        $from = $this->from('schools');
        $min = (int) config('testacbt.school_board_min_members');

        $rows = $this->base($from)->whereNotNull('u.school_key')
            ->groupBy('u.school_key')
            ->selectRaw('u.school_key, MAX(u.school) as school, COUNT(DISTINCT u.id) as members, SUM(' . self::POINTS . ') as total')
            ->havingRaw('COUNT(DISTINCT u.id) >= ?', [$min])->get()
            ->map(fn ($r) => ['school' => $r->school, 'members' => (int) $r->members, 'points' => (int) round($r->total / max(1, $r->members)), 'mine' => $me && $me->school_key === $r->school_key])
            ->sortByDesc('points')->take(20)->values();

        $rank = 0;
        $prev = null;
        $out = $rows->map(function ($r, $i) use (&$rank, &$prev) {
            if ($prev === null || $r['points'] !== $prev) { $rank = $i + 1; }
            $prev = $r['points'];
            return ['rank' => $rank] + $r;
        })->all();

        return ['scope' => 'schools', 'period' => 'This week', 'rows' => $out, 'me' => null, 'min_members' => $min];
    }
}
