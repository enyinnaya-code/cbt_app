<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Today's challenge for one student: a fixed set of questions from one subject, a different subject most days.
 * It counts as complete when every question has an answer recorded today, so it works the same whether the answers
 * came from the website or were synced from the phone. Completing it pays the XP bonus once.
 */
class DailyChallenge
{
    public function __construct(private User $user, private QuestionSelector $selector) {}

    /** Today's challenge, created on first use. Null when there is nothing to practise yet. */
    public function today(): ?object
    {
        $date = now()->toDateString();

        $row = DB::table('daily_challenges')->where('user_id', $this->user->id)->where('challenge_date', $date)->first();
        if ($row) {
            return $this->hydrate($row);
        }

        $picked = $this->pick($date);
        if (! $picked) {
            return null;
        }

        DB::table('daily_challenges')->insertOrIgnore([
            'user_id' => $this->user->id, 'challenge_date' => $date, 'question_ids' => json_encode($picked['ids']),
            'started_at' => now(), 'total' => count($picked['ids']), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $this->hydrate(DB::table('daily_challenges')->where('user_id', $this->user->id)->where('challenge_date', $date)->first());
    }

    /**
     * Picks the subject by date, so it rotates, from the student's exams. Locked subjects only offer their free
     * sample (Access decides), and a subject with too few questions is skipped.
     *
     * @return array{ids:array<int,int>,exam:Exam,subject:Subject}|null
     */
    private function pick(string $date): ?array
    {
        $access = Access::for($this->user);
        $exams = Exam::where('is_active', true)->orderBy('sort_order')->get();

        $first = $exams->firstWhere('slug', $this->user->target_exam)
            ?? $exams->firstWhere('slug', ($this->user->preferred_exams ?? [])[0] ?? null);
        if ($first) {
            $exams = $exams->prepend($first)->unique('id')->values();
        }

        $seed = crc32($this->user->id . '|' . $date);
        $size = (int) config('testacbt.challenge_size');
        $min = (int) config('testacbt.challenge_min');

        foreach ($exams as $exam) {
            $available = $this->selector->availability($exam);
            $subjects = $exam->subjects()->where('subjects.is_active', true)->orderBy('subjects.id')->get()
                ->filter(fn ($s) => ($available[$s->id] ?? 0) >= $min)->values();
            if ($subjects->isEmpty()) {
                continue;
            }

            // Start from the subject chosen for today and walk on until one has enough questions the student may open.
            $start = $seed % $subjects->count();
            for ($k = 0; $k < $subjects->count(); $k++) {
                $subject = $subjects[($start + $k) % $subjects->count()];
                $picked = $this->selector->practice($exam, $subject, null, $size, null, $seed, $access);
                $ids = array_column($picked['questions'], 'id');
                if (count($ids) >= $min) {
                    return ['ids' => $ids, 'exam' => $exam, 'subject' => $subject];
                }
            }
        }

        return null;
    }

    private function hydrate(object $row): object
    {
        $ids = json_decode($row->question_ids, true) ?: [];
        $answered = $this->answeredToday($row, $ids);

        $row->ids = $ids;
        $row->answered = $answered['answered'];
        $row->right = $answered['correct'];
        $row->done = $row->completed_at !== null;
        $row->subject = DB::table('questions as q')->join('papers as p', 'p.id', '=', 'q.paper_id')->join('subjects as s', 's.id', '=', 'p.subject_id')
            ->where('q.id', $ids[0] ?? 0)->value('s.name');
        $row->xp = (int) config('testacbt.xp.challenge_bonus');

        return $row;
    }

    /** @return array{answered:int,correct:int} distinct questions answered on the challenge date, and how many were right */
    private function answeredToday(object $row, array $ids): array
    {
        if (! $ids) {
            return ['answered' => 0, 'correct' => 0];
        }

        $rows = DB::table('question_attempts')->where('user_id', $this->user->id)->whereIn('question_id', $ids)
            ->whereDate('answered_at', $row->challenge_date)->orderBy('answered_at')->get(['question_id', 'is_correct']);

        // The first answer to each question is the one that counts.
        $first = $rows->groupBy('question_id')->map(fn ($g) => (bool) $g->first()->is_correct);

        return ['answered' => $first->count(), 'correct' => $first->filter()->count()];
    }

    /**
     * Marks today's challenge complete when every question has been answered, and pays the bonus once.
     *
     * @return array{done:bool,newly:bool,correct:int,total:int,xp:int}|null null when there is no challenge today
     */
    public function complete(): ?array
    {
        $c = $this->today();
        if (! $c) {
            return null;
        }

        if ($c->done) {
            return ['done' => true, 'newly' => false, 'correct' => (int) $c->correct, 'total' => (int) $c->total, 'xp' => (int) $c->xp_awarded];
        }

        if ($c->answered < $c->total) {
            return ['done' => false, 'newly' => false, 'correct' => $c->right, 'total' => (int) $c->total, 'xp' => 0];
        }

        // The where() makes a double submit pay once.
        $updated = DB::table('daily_challenges')->where('id', $c->id)->whereNull('completed_at')->update([
            'completed_at' => now(), 'correct' => $c->right, 'xp_awarded' => $c->xp, 'updated_at' => now(),
        ]);

        return ['done' => true, 'newly' => $updated === 1, 'correct' => $c->right, 'total' => (int) $c->total, 'xp' => $updated ? $c->xp : 0];
    }

    /**
     * A challenge finished on the phone (which builds its own questions while offline). One challenge a day pays the bonus,
     * wherever it was done: if the website already paid that day this does nothing, and if the website's challenge was
     * started but not finished the phone's result completes it. The score is recounted from the stored answers.
     *
     * @param  array<int,int>  $ids
     * @return array{done:bool,newly:bool,correct:int,total:int,xp:int}|null  null when the claim is refused
     */
    public function claim(string $date, array $ids): ?array
    {
        // Only students who joined the challenge can earn its bonus or appear on its rankings.
        if (! \App\Services\Leaderboard::isParticipant($this->user)) {
            return null;
        }

        $day = \Illuminate\Support\Carbon::parse($date)->startOfDay();
        if ($day->isFuture() || $day->lt(now()->subDays(3)->startOfDay())) {
            return null;
        }

        $access = Access::for($this->user);
        $ids = \App\Models\Question::whereIn('id', array_unique($ids))->with('paper:id,exam_id,subject_id')->get(['id', 'paper_id'])
            ->filter(fn ($q) => $access->allowsQuestion($q))->pluck('id')->values()->all();
        if (count($ids) < (int) config('testacbt.challenge_min')) {
            return null;
        }

        // Answers made around the phone's calendar day count: the server's clock may be an hour or so off the phone's.
        $first = DB::table('question_attempts')->where('user_id', $this->user->id)->whereIn('question_id', $ids)
            ->where('answered_at', '>=', $day->copy()->subDay())->where('answered_at', '<', $day->copy()->addDays(2))
            ->orderBy('answered_at')->get(['question_id', 'is_correct'])->groupBy('question_id')->map(fn ($g) => (bool) $g->first()->is_correct);
        $correct = $first->filter()->count();
        $complete = $first->count() >= count($ids);

        $row = DB::table('daily_challenges')->where('user_id', $this->user->id)->where('challenge_date', $day->toDateString())->first();
        if ($row && $row->completed_at !== null) {
            return ['done' => true, 'newly' => false, 'correct' => (int) $row->correct, 'total' => (int) $row->total, 'xp' => (int) $row->xp_awarded];
        }
        if (! $complete) {
            return ['done' => false, 'newly' => false, 'correct' => $correct, 'total' => count($ids), 'xp' => 0];
        }

        $bonus = (int) config('testacbt.xp.challenge_bonus');
        if ($row) {
            $paid = DB::table('daily_challenges')->where('id', $row->id)->whereNull('completed_at')->update([
                'completed_at' => now(), 'correct' => $correct, 'total' => count($ids), 'question_ids' => json_encode($ids), 'xp_awarded' => $bonus, 'updated_at' => now(),
            ]);
        } else {
            $paid = DB::table('daily_challenges')->insertOrIgnore([
                'user_id' => $this->user->id, 'challenge_date' => $day->toDateString(), 'question_ids' => json_encode($ids), 'started_at' => now(),
                'completed_at' => now(), 'correct' => $correct, 'total' => count($ids), 'xp_awarded' => $bonus, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return ['done' => true, 'newly' => $paid === 1, 'correct' => $correct, 'total' => count($ids), 'xp' => $paid ? $bonus : 0];
    }
}
