<?php

namespace App\Services;

use App\Models\Entitlement;
use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * What one student may see. Everyone gets the free sample of every subject; a purchase (an entitlement) or a
 * price of 0 opens the whole subject; examiners and admins see everything.
 *
 * The free sample is the first N usable questions, newest year first. It is worked out here, in one place, so the
 * website, the offline packs and the answer-recording rules can never disagree about which questions are free.
 */
class Access
{
    /** How long the free question ids are remembered. A new paper reaches the free sample within this time. */
    private const FREE_CACHE_SECONDS = 300;

    /** @var array<string,object>|null exam_subject rows by "exam-subject" */
    private ?array $pivots = null;

    /** @var array<string,Carbon>|null active purchases by "exam-subject" */
    private ?array $expiry = null;

    /** @var array<string,array<int,int>> free question ids (as keys) by "exam-subject" */
    private array $free = [];

    public function __construct(private User $user, private QuestionSelector $selector) {}

    public static function for(User $user): self
    {
        return new self($user, app(QuestionSelector::class));
    }

    public function isStaff(): bool
    {
        return $this->user->canManageQuestions();
    }

    /** Whole subject open: staff, a free subject, or a purchase that has not run out. */
    public function full(int $examId, int $subjectId): bool
    {
        return $this->isStaff() || $this->price($examId, $subjectId) === 0 || $this->expiresAt($examId, $subjectId) !== null;
    }

    /** When the student's purchase ends, or null when they have not bought it (or it has run out). */
    public function expiresAt(int $examId, int $subjectId): ?Carbon
    {
        $this->expiry ??= Entitlement::active()->where('user_id', $this->user->id)->get()
            ->mapWithKeys(fn ($e) => [$e->exam_id . '-' . $e->subject_id => $e->expires_at])->all();

        return $this->expiry[$examId . '-' . $subjectId] ?? null;
    }

    public function price(int $examId, int $subjectId): int
    {
        return Pricing::price($this->pivot($examId, $subjectId));
    }

    /** How many questions of this subject are free. */
    public function freeLimit(int $examId, int $subjectId): int
    {
        return Pricing::freeQuestions($this->pivot($examId, $subjectId));
    }

    /** @return array<int,int> question ids that are free, as [id => id] */
    public function freeIds(int $examId, int $subjectId): array
    {
        $key = $examId . '-' . $subjectId;

        return $this->free[$key] ??= (function () use ($examId, $subjectId) {
            $limit = $this->freeLimit($examId, $subjectId);
            $ids = Cache::remember("free-ids:{$examId}:{$subjectId}:{$limit}", self::FREE_CACHE_SECONDS, fn () => $this->selector->freeIds($examId, $subjectId, $limit));

            return array_combine($ids, $ids) ?: [];
        })();
    }

    /** Can this student see this question (and so its answer)? */
    public function allows(int $examId, int $subjectId, int $questionId): bool
    {
        return $this->full($examId, $subjectId) || isset($this->freeIds($examId, $subjectId)[$questionId]);
    }

    public function allowsQuestion(Question $question): bool
    {
        $paper = $question->paper;

        // A question that is not part of any paper is not sold content; nothing to gate.
        return $paper === null || $this->allows((int) $paper->exam_id, (int) $paper->subject_id, (int) $question->id);
    }

    private function pivot(int $examId, int $subjectId): ?object
    {
        $this->pivots ??= DB::table('exam_subject')->get()->keyBy(fn ($r) => $r->exam_id . '-' . $r->subject_id)->all();

        return $this->pivots[$examId . '-' . $subjectId] ?? null;
    }
}
