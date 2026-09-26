<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Test;
use Illuminate\Support\Collection;

/**
 * Turns a pre-TestaCBT school test into an exam paper (exam + subject + year) and attaches its questions.
 * Used by both the console screen and the testacbt:tag-legacy command, so they follow the same rules.
 */
class LegacyTagger
{
    public const FIRST_YEAR = 1970;

    public static function yearIsValid(int|string $year): bool
    {
        return ctype_digit((string) $year) && (int) $year >= self::FIRST_YEAR && (int) $year <= (int) date('Y') + 1;
    }

    /** Old tests that have not been turned into a paper yet. */
    public function untagged(): Collection
    {
        $tagged = Paper::whereNotNull('legacy_test_id')->pluck('legacy_test_id');

        return Test::whereNotIn('id', $tagged)->orderBy('id')->get()
            ->each(fn (Test $t) => $t->setAttribute('question_count', Question::where('test_id', $t->id)->count()));
    }

    public function untaggedCount(): int
    {
        return Test::whereNotIn('id', Paper::whereNotNull('legacy_test_id')->select('legacy_test_id'))->count();
    }

    /**
     * Safe to call again for the same test: the paper is found by its legacy id and only questions
     * that have no paper yet are moved.
     *
     * @return array{paper:Paper, created:bool, moved:int}
     */
    public function tag(Test $test, Exam $exam, Subject $subject, int $year, bool $publish = false): array
    {
        $paper = Paper::firstOrCreate(
            ['legacy_test_id' => $test->id],
            [
                'exam_id' => $exam->id,
                'subject_id' => $subject->id,
                'year' => $year,
                'title' => $test->test_name,
                'duration_minutes' => $test->duration,
                'created_by' => $test->created_by,
                'status' => $publish ? Paper::PUBLISHED : Paper::DRAFT,
                'published_at' => $publish ? now() : null,
            ]
        );

        $moved = Question::where('test_id', $test->id)->whereNull('paper_id')->update(['paper_id' => $paper->id]);

        return ['paper' => $paper, 'created' => $paper->wasRecentlyCreated, 'moved' => $moved];
    }
}
