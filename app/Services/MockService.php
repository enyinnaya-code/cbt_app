<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\MockRun;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Timed mock exams. The question list, the clock and the marking all live on the server, so a refresh,
 * a dropped connection or a second device cannot change the paper or restart the timer.
 */
class MockService
{
    /** A subject needs at least this many usable questions before it is offered in a mock. */
    public const MIN_QUESTIONS = 5;

    public function __construct(private QuestionSelector $selector) {}

    /** The exam's format from config/testacbt.php, with a sensible single-subject default for other exams. */
    public function format(Exam $exam): array
    {
        $configured = config("testacbt.mock.{$exam->slug}");

        return $configured ?: [
            'label' => $exam->name . ' mock', 'subject_count' => 1, 'questions' => ['default' => 50], 'minutes' => 60, 'score_max' => 100,
        ];
    }

    public function plannedQuestions(array $format, string $subjectSlug): int
    {
        return (int) ($format['questions'][$subjectSlug] ?? $format['questions']['default']);
    }

    /**
     * Subjects that have enough questions for a mock, with the count the exam would use for each.
     *
     * @return Collection<int, object{id:int,slug:string,name:string,code:string,available:int,planned:int,compulsory:bool}>
     */
    public function subjectOptions(Exam $exam): Collection
    {
        $format = $this->format($exam);
        $available = $this->selector->availability($exam);

        return $exam->subjects()->where('subjects.is_active', true)->orderBy('subjects.name')->get()
            ->map(fn ($s) => (object) [
                'id' => $s->id, 'slug' => $s->slug, 'code' => $s->code,
                'name' => $s->pivot->display_name ?: $s->name,
                'available' => (int) ($available[$s->id] ?? 0),
                'planned' => $this->plannedQuestions($format, $s->slug),
                'compulsory' => ($format['compulsory'] ?? null) === $s->slug,
            ])
            ->filter(fn ($s) => $s->available >= self::MIN_QUESTIONS)
            ->sortByDesc('compulsory')->values();
    }

    /**
     * @param  array<int,string>  $subjectSlugs
     * @throws ValidationException when the choice does not fit the exam's format
     */
    public function start(User $user, Exam $exam, array $subjectSlugs, ?int $seed = null): MockRun
    {
        $format = $this->format($exam);
        $options = $this->subjectOptions($exam)->keyBy('slug');
        $slugs = array_values(array_unique($subjectSlugs));

        $fail = fn (string $message) => throw ValidationException::withMessages(['subjects' => $message]);

        if (count($slugs) !== $format['subject_count']) {
            $fail($format['subject_count'] === 1 ? 'Choose one subject.' : "Choose exactly {$format['subject_count']} subjects.");
        }
        if (! empty($format['compulsory']) && ! in_array($format['compulsory'], $slugs, true)) {
            $fail('Use of English is compulsory for this exam.');
        }
        foreach ($slugs as $slug) {
            if (! $options->has($slug)) { $fail('One of those subjects is not available for this exam yet.'); }
        }

        // Compulsory subject first, the rest A to Z.
        usort($slugs, fn ($a, $b) => [$options[$b]->compulsory, $options[$a]->name] <=> [$options[$a]->compulsory, $options[$b]->name]);

        $ids = [];
        $groups = [];
        $planned = 0;

        foreach ($slugs as $slug) {
            $subject = Subject::where('slug', $slug)->firstOrFail();
            $want = $this->plannedQuestions($format, $slug);
            $chosen = $this->selector->mockIds($exam, $subject, $want, $seed);

            $planned += $want;
            $ids = array_merge($ids, $chosen);
            $groups[] = ['subject_id' => $subject->id, 'name' => $options[$slug]->name, 'count' => count($chosen)];
        }

        // If there are fewer questions than the real exam uses, shorten the clock in proportion.
        $minutes = $format['minutes'];
        if (count($ids) < $planned) {
            $minutes = max(5, (int) ceil($format['minutes'] * count($ids) / $planned));
        }

        $now = now();

        return MockRun::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'subject_ids' => array_column($groups, 'subject_id'),
            'question_ids' => $ids,
            'groups' => $groups,
            'minutes' => $minutes,
            'started_at' => $now,
            'deadline_at' => $now->copy()->addMinutes($minutes),
            'answers' => [],
            'flagged' => [],
        ]);
    }

    public function active(User $user): ?MockRun
    {
        return MockRun::where('user_id', $user->id)->whereNull('submitted_at')->latest('id')->first();
    }

    /** Autosave while the student works. Ignored once the exam is submitted. */
    public function save(MockRun $run, array $answers, array $flagged, int $position): void
    {
        if ($run->isSubmitted()) { return; }

        $allowed = array_flip($run->question_ids);

        $run->update([
            'answers' => $this->cleanAnswers($answers, $allowed),
            'flagged' => array_values(array_filter(array_map('intval', $flagged), fn ($id) => isset($allowed[$id]))),
            'position' => max(0, min($position, count($run->question_ids) - 1)),
        ]);
    }

    /** Marks the exam, records the answers in the student's progress, and stores the result. Safe to call twice. */
    public function submit(MockRun $run, ?array $answers = null): MockRun
    {
        if ($run->isSubmitted()) { return $run; }

        return DB::transaction(function () use ($run, $answers) {
            $run = MockRun::whereKey($run->id)->lockForUpdate()->first();
            if ($run->isSubmitted()) { return $run; }

            $now = now();
            $allowed = array_flip($run->question_ids);
            $given = $this->cleanAnswers($answers ?? ($run->answers ?? []), $allowed);

            $questions = Question::whereIn('id', $run->question_ids)->with('topic:id,name')->get()->keyBy('id');

            // Walk the questions in exam order, group by group.
            $offset = 0;
            $groups = [];
            $topics = [];
            $correctTotal = 0;
            $answeredTotal = 0;

            foreach ($run->groups as $g) {
                $ids = array_slice($run->question_ids, $offset, $g['count']);
                $offset += $g['count'];
                $correct = $answered = 0;

                foreach ($ids as $id) {
                    $q = $questions->get($id);
                    $picked = $given[$id] ?? null;
                    $right = $q && $picked !== null && $picked === strtoupper(trim((string) $q->answer));

                    $picked !== null && $answered++;
                    $right && $correct++;

                    if ($q?->topic) {
                        $t = $topics[$q->topic->id] ?? ['topic_id' => $q->topic->id, 'name' => $q->topic->name, 'correct' => 0, 'total' => 0];
                        $t['total']++;
                        $right && $t['correct']++;
                        $topics[$q->topic->id] = $t;
                    }
                }

                $total = count($ids);
                $groups[] = [
                    'subject_id' => $g['subject_id'], 'name' => $g['name'], 'correct' => $correct, 'answered' => $answered,
                    'total' => $total, 'percent' => $total ? (int) round($correct / $total * 100) : 0,
                ];
                $correctTotal += $correct;
                $answeredTotal += $answered;
            }

            $score = array_sum(array_column($groups, 'percent'));
            $max = 100 * count($groups);
            $used = (int) min($run->minutes * 60, max(0, $run->started_at->diffInSeconds($now)));

            // Feed the student's progress the same way practice does.
            $recorder = new ProgressRecorder($run->user);
            $recorder->attempts(collect($given)->map(fn ($letter, $qid) => [
                'client_uuid' => (string) Str::uuid(), 'question_id' => (int) $qid, 'mode' => 'mock', 'selected' => $letter,
                'answered_at' => $now->toIso8601String(),
            ])->values()->all(), $now);

            $recorder->mockSessions([[
                'client_uuid' => $run->uuid, 'exam_id' => $run->exam_id, 'score' => $score, 'total' => $max,
                'duration_seconds' => $used, 'taken_at' => $now->toIso8601String(),
                'subject_scores' => array_map(fn ($g) => ['subject_id' => $g['subject_id'], 'correct' => $g['correct'], 'total' => $g['total']], $groups),
            ]], $now);

            $run->update([
                'answers' => $given,
                'submitted_at' => $now,
                'score' => $score,
                'total' => $max,
                'result' => [
                    'groups' => $groups,
                    'correct' => $correctTotal,
                    'answered' => $answeredTotal,
                    'questions' => count($run->question_ids),
                    'duration_seconds' => $used,
                    'topics' => array_values($topics),
                ],
            ]);

            return $run->refresh();
        });
    }

    /** Points versus the student's previous mock for the same exam, or null when this is their first. */
    public function changeSincePrevious(MockRun $run): ?int
    {
        if (! $run->isSubmitted()) { return null; }

        $previous = MockRun::where('user_id', $run->user_id)->where('exam_id', $run->exam_id)
            ->whereNotNull('submitted_at')->where('id', '<', $run->id)->where('total', $run->total)
            ->latest('id')->first();

        return $previous ? $run->score - $previous->score : null;
    }

    /** @return array<int,string> question id => letter, only for this exam's questions and valid letters */
    private function cleanAnswers(array $answers, array $allowed): array
    {
        $clean = [];

        foreach ($answers as $qid => $letter) {
            $letter = strtoupper((string) $letter);
            if (isset($allowed[(int) $qid]) && preg_match('/^[A-E]$/', $letter)) {
                $clean[(int) $qid] = $letter;
            }
        }

        return $clean;
    }
}
