<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Paper;
use App\Models\Subject;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the downloadable packs in step with the published papers without anyone having to remember to press
 * "rebuild". Anything that changes what students can see (publishing or unpublishing a paper, adding, editing or
 * deleting a question in a live paper, renaming a topic) marks that exam and subject, and the pack is rebuilt once,
 * when the request or command finishes. An import of 500 questions therefore causes one rebuild, not 500.
 */
class PackRefresher
{
    /** @var array<string, array{0:int,1:int}> */
    private array $pending = [];

    private bool $waiting = false;

    public function __construct(private PackBuilder $builder) {}

    public function enabled(): bool
    {
        return (bool) config('testacbt.auto_packs', true);
    }

    public function mark(?int $examId, ?int $subjectId): void
    {
        if (! $this->enabled() || ! $examId || ! $subjectId) {
            return;
        }

        $this->pending["{$examId}:{$subjectId}"] = [$examId, $subjectId];

        if (! $this->waiting) {
            $this->waiting = true;
            app()->terminating(fn () => $this->flush());
        }
    }

    /** A change to a paper that students can see (or could see until now). */
    public function markPaper(Paper $paper): void
    {
        if ($paper->status === Paper::PUBLISHED || $paper->getOriginal('status') === Paper::PUBLISHED) {
            $this->mark($paper->exam_id, $paper->subject_id);
            // A paper moved to another exam or subject leaves a pack behind that must lose it.
            $this->mark((int) ($paper->getOriginal('exam_id') ?? $paper->exam_id), (int) ($paper->getOriginal('subject_id') ?? $paper->subject_id));
        }
    }

    /** Builds what was marked. Never throws: a failed rebuild is logged and the admin can use Packs > Rebuild. */
    public function flush(): void
    {
        $pairs = $this->pending;
        $this->pending = [];
        $this->waiting = false;

        foreach ($pairs as [$examId, $subjectId]) {
            try {
                $exam = Exam::find($examId);
                $subject = Subject::find($subjectId);
                if ($exam && $subject) {
                    $this->builder->build($exam, $subject);
                }
            } catch (\Throwable $e) {
                Log::error('Automatic pack rebuild failed', ['exam_id' => $examId, 'subject_id' => $subjectId, 'error' => $e->getMessage()]);
            }
        }
    }
}
