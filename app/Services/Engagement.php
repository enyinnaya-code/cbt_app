<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * XP, level and badges for one student. Nothing here is a counter that can drift: XP and streaks are worked out from
 * question_attempts (the same rows the phone syncs), plus the bonus on each completed daily challenge. Only the badges
 * a student has earned are stored, so they are never taken away if a rule changes.
 */
class Engagement
{
    /** @var array<string, array{title:string,text:string,icon:string}> */
    public const BADGES = [
        'first_steps' => ['title' => 'First steps', 'text' => 'Answered your first 10 questions', 'icon' => 'play'],
        'century' => ['title' => 'Century', 'text' => '100 correct answers', 'icon' => 'check'],
        'streak_7' => ['title' => '7-day streak', 'text' => 'Practised seven days in a row', 'icon' => 'flame'],
        'first_challenge' => ['title' => 'Challenger', 'text' => 'Completed a daily challenge', 'icon' => 'clock'],
        'challenge_5' => ['title' => 'Regular', 'text' => 'Completed 5 daily challenges', 'icon' => 'chart'],
        'first_mock' => ['title' => 'First mock', 'text' => 'Finished your first mock exam', 'icon' => 'book'],
        'perfect_score' => ['title' => 'Perfect score', 'text' => '100% on a challenge or mock exam', 'icon' => 'trophy'],
        'comeback' => ['title' => 'Comeback', 'text' => 'Beat your previous mock score by 10 points', 'icon' => 'chart'],
    ];

    public function __construct(private User $user) {}

    // ---------------------------------------------------------------- XP and level

    /** @return Collection<int, object{day:string,n:int,right:int}> */
    private function days(): Collection
    {
        return DB::table('question_attempts')->where('user_id', $this->user->id)
            ->selectRaw('DATE(answered_at) as day, COUNT(*) as n, SUM(is_correct) as right_count')
            ->groupBy('day')->get()
            ->map(fn ($r) => (object) ['day' => $r->day, 'n' => (int) $r->n, 'right' => (int) $r->right_count]);
    }

    public function xp(): int
    {
        $xp = config('testacbt.xp');
        $fromAnswers = $this->days()->sum(fn ($d) => min(
            $d->right * $xp['correct'] + ($d->n - $d->right) * $xp['wrong'],
            $xp['daily_answer_cap'],
        ));
        $bonus = (int) DB::table('daily_challenges')->where('user_id', $this->user->id)->sum('xp_awarded');

        return (int) $fromAnswers + $bonus;
    }

    /**
     * @return array{level:int,title:string,xp:int,floor:int,next:?int,next_title:?string,pct:int,to_next:?int}
     */
    public function level(?int $xp = null): array
    {
        $xp ??= $this->xp();
        $levels = config('testacbt.levels');

        $i = 0;
        foreach ($levels as $n => $row) {
            if ($xp >= $row['xp']) { $i = $n; }
        }

        $next = $levels[$i + 1] ?? null;
        $floor = $levels[$i]['xp'];

        return [
            'level' => $i + 1,
            'title' => $levels[$i]['title'],
            'xp' => $xp,
            'floor' => $floor,
            'next' => $next['xp'] ?? null,
            'next_title' => $next['title'] ?? null,
            'pct' => $next ? (int) floor(($xp - $floor) / ($next['xp'] - $floor) * 100) : 100,
            'to_next' => $next ? $next['xp'] - $xp : null,
        ];
    }

    /** XP earned today from answers, so Home can show "+120 XP today". */
    public function xpToday(): int
    {
        $xp = config('testacbt.xp');
        $d = $this->days()->firstWhere('day', Carbon::today()->toDateString());

        return $d ? min($d->right * $xp['correct'] + ($d->n - $d->right) * $xp['wrong'], $xp['daily_answer_cap']) : 0;
    }

    public function longestStreak(): int
    {
        $days = $this->days()->pluck('day')->map(fn ($d) => Carbon::parse($d))->sort()->values();
        $best = $run = 0;
        $prev = null;

        foreach ($days as $d) {
            $run = $prev && $prev->copy()->addDay()->isSameDay($d) ? $run + 1 : 1;
            $best = max($best, $run);
            $prev = $d;
        }

        return $best;
    }

    // ---------------------------------------------------------------- badges

    /** @return Collection<string, string> badge key => earned_at */
    public function earned(): Collection
    {
        return DB::table('user_badges')->where('user_id', $this->user->id)->orderBy('earned_at')->pluck('earned_at', 'badge');
    }

    /** Awards any badge the student now qualifies for. Returns the keys that are new. @return array<int, string> */
    public function award(): array
    {
        $earned = $this->earned();
        if ($earned->count() === count(self::BADGES)) { return []; }

        $answered = (int) DB::table('question_attempts')->where('user_id', $this->user->id)->count();
        $correct = (int) DB::table('question_attempts')->where('user_id', $this->user->id)->where('is_correct', 1)->count();

        $challenges = DB::table('daily_challenges')->where('user_id', $this->user->id)->whereNotNull('completed_at');
        $challengesDone = (clone $challenges)->count();
        $perfectChallenge = (clone $challenges)->whereColumn('correct', 'total')->exists();

        // mock_sessions holds every finished mock, whether it was sat on the website or on the phone.
        $mocks = DB::table('mock_sessions')->where('user_id', $this->user->id)->where('total', '>', 0)
            ->orderBy('taken_at')->get(['exam_id', 'score', 'total']);
        $perfectMock = $mocks->contains(fn ($m) => $m->score >= $m->total);

        $comeback = $mocks->groupBy('exam_id')->contains(function ($runs) {
            $pcts = $runs->map(fn ($m) => $m->score / $m->total * 100)->values();
            for ($i = 1; $i < $pcts->count(); $i++) {
                if ($pcts[$i] - $pcts[$i - 1] >= 10) { return true; }
            }
            return false;
        });

        $qualifies = [
            'first_steps' => $answered >= 10,
            'century' => $correct >= 100,
            'streak_7' => $this->longestStreak() >= 7,
            'first_challenge' => $challengesDone >= 1,
            'challenge_5' => $challengesDone >= 5,
            'first_mock' => $mocks->isNotEmpty(),
            'perfect_score' => $perfectChallenge || $perfectMock,
            'comeback' => $comeback,
        ];

        $new = [];
        foreach ($qualifies as $badge => $yes) {
            if ($yes && ! $earned->has($badge)) { $new[] = $badge; }
        }

        if ($new) {
            DB::table('user_badges')->insertOrIgnore(array_map(fn ($b) => [
                'user_id' => $this->user->id, 'badge' => $b, 'earned_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ], $new));
        }

        return $new;
    }

    /** Every badge with whether it has been earned, earned ones first. @return Collection<int, array<string,mixed>> */
    public function badges(): Collection
    {
        $earned = $this->earned();

        return collect(self::BADGES)->map(fn ($b, $key) => $b + [
            'key' => $key, 'earned' => $earned->has($key), 'earned_at' => $earned->get($key),
        ])->sortByDesc('earned')->values();
    }

    /** One warm line for the Home page, picked from what the student actually did. */
    public function message(StudentStats $stats): string
    {
        $today = $stats->lastDays(2)[1]['count'] ?? 0;
        $streak = $stats->streak();

        return match (true) {
            $streak >= 7 => "{$streak} days in a row. That consistency is what passes exams.",
            $streak >= 2 && $today === 0 => "Practise today to keep your {$streak}-day streak going.",
            $today >= 10 => 'Great session today. You are getting stronger.',
            $today > 0 => 'Good start. A few more questions keeps the momentum going.',
            default => 'A short session today beats a long one next week.',
        };
    }
}
