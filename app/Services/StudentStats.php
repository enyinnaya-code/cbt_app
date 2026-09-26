<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Numbers shown on the student's Home and Progress pages, all derived from question_attempts.
 */
class StudentStats
{
    public function __construct(private User $user) {}

    public function totalAnswered(): int
    {
        return (int) DB::table('question_attempts')->where('user_id', $this->user->id)->count();
    }

    /** Consecutive days, ending today (or yesterday, so a streak is not lost before the student has studied today). */
    public function streak(): int
    {
        $days = DB::table('question_attempts')->where('user_id', $this->user->id)
            ->pluck('answered_at')
            ->map(fn ($t) => Carbon::parse($t)->toDateString())
            ->unique()->flip();

        $day = Carbon::today();
        if (! $days->has($day->toDateString())) {
            $day = $day->subDay();
        }

        $streak = 0;
        while ($days->has($day->toDateString())) {
            $streak++;
            $day = $day->subDay();
        }

        return $streak;
    }

    /**
     * Accuracy per subject, weakest first.
     *
     * @return Collection<int, object{subject_id:int,name:string,code:string,answered:int,correct:int,accuracy:int}>
     */
    public function subjects(): Collection
    {
        return DB::table('question_attempts as a')
            ->join('questions as q', 'q.id', '=', 'a.question_id')
            ->join('papers as p', 'p.id', '=', 'q.paper_id')
            ->join('subjects as s', 's.id', '=', 'p.subject_id')
            ->where('a.user_id', $this->user->id)
            ->groupBy('s.id', 's.name', 's.code')
            ->select('s.id as subject_id', 's.name', 's.code', DB::raw('COUNT(*) as answered'), DB::raw('SUM(a.is_correct) as correct'))
            ->get()
            ->map(function ($r) {
                $r->answered = (int) $r->answered;
                $r->correct = (int) $r->correct;
                $r->accuracy = $r->answered ? (int) round($r->correct / $r->answered * 100) : 0;
                return $r;
            })
            ->sortBy('accuracy')->values();
    }

    /** Topics with at least $min answers, weakest first. Empty until examiners tag questions with topics. */
    public function weakTopics(int $limit = 5, int $min = 5): Collection
    {
        return DB::table('question_attempts as a')
            ->join('questions as q', 'q.id', '=', 'a.question_id')
            ->join('topics as t', 't.id', '=', 'q.topic_id')
            ->join('subjects as s', 's.id', '=', 't.subject_id')
            ->where('a.user_id', $this->user->id)
            ->groupBy('t.id', 't.name', 's.id', 's.name', 's.slug')
            ->havingRaw('COUNT(*) >= ?', [$min])
            ->select('t.id as topic_id', 't.name as topic', 's.id as subject_id', 's.name as subject', 's.slug as subject_slug', DB::raw('COUNT(*) as answered'), DB::raw('SUM(a.is_correct) as correct'))
            ->get()
            ->map(function ($r) {
                $r->accuracy = (int) round($r->correct / $r->answered * 100);
                return $r;
            })
            ->sortBy('accuracy')->take($limit)->values();
    }

    /**
     * Questions answered on each of the last $days days, oldest first.
     *
     * @return array<int, array{label:string,date:string,count:int,today:bool}>
     */
    public function lastDays(int $days = 7): array
    {
        $from = Carbon::today()->subDays($days - 1);
        $counts = DB::table('question_attempts')->where('user_id', $this->user->id)
            ->where('answered_at', '>=', $from)->pluck('answered_at')
            ->map(fn ($t) => Carbon::parse($t)->toDateString())
            ->countBy();

        return collect(range(0, $days - 1))->map(function ($i) use ($from, $counts) {
            $d = $from->copy()->addDays($i);
            return ['label' => $d->format('D'), 'date' => $d->toDateString(), 'count' => (int) ($counts[$d->toDateString()] ?? 0), 'today' => $d->isToday()];
        })->all();
    }

    /** The exam + subject the student last practised, so Home can offer "carry on". */
    public function lastPractised(): ?object
    {
        return DB::table('question_attempts as a')
            ->join('questions as q', 'q.id', '=', 'a.question_id')
            ->join('papers as p', 'p.id', '=', 'q.paper_id')
            ->join('exams as e', 'e.id', '=', 'p.exam_id')
            ->join('subjects as s', 's.id', '=', 'p.subject_id')
            ->where('a.user_id', $this->user->id)
            ->orderByDesc('a.id')
            ->select('e.slug as exam_slug', 'e.name as exam', 's.slug as subject_slug', 's.name as subject', 's.code', 'p.year', 'a.answered_at')
            ->first();
    }

    public function daysToExam(): ?int
    {
        if (! $this->user->target_exam_date) {
            return null;
        }

        $days = Carbon::today()->diffInDays($this->user->target_exam_date, false);

        return $days >= 0 ? (int) $days : null;
    }
}
