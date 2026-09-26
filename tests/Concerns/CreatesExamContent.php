<?php

namespace Tests\Concerns;

use App\Models\Exam;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesExamContent
{
    private int $userSeq = 0;

    protected function makeUser(int $type = 4, array $extra = []): User
    {
        return User::create($extra + [
            'name' => 'User ' . ++$this->userSeq,
            'email' => "u{$this->userSeq}@example.com",
            'password' => 'password123',
            'user_type' => $type,
        ]);
    }

    /** Subjects made here are free (price 0) unless a test says otherwise, so tests about practice and mocks need no purchase. */
    protected function makeExamAndSubject(string $exam = 'WAEC', string $subject = 'Physics', ?string $display = null, ?int $price = 0): array
    {
        $e = Exam::firstOrCreate(['slug' => Str::slug($exam)], ['name' => $exam]);
        $s = Subject::firstOrCreate(['slug' => Str::slug($subject)], ['name' => $subject, 'code' => substr($subject, 0, 2)]);
        $e->subjects()->syncWithoutDetaching([$s->id => ['display_name' => $display, 'price' => $price]]);

        return [$e, $s];
    }

    protected function makePaper(Exam $e, Subject $s, int $year = 2019, string $status = Paper::PUBLISHED): Paper
    {
        return Paper::create([
            'exam_id' => $e->id, 'subject_id' => $s->id, 'year' => $year, 'title' => "{$e->name} {$s->name} $year",
            'status' => $status, 'published_at' => $status === Paper::PUBLISHED ? now() : null,
        ]);
    }

    protected function makeQuestion(Paper $paper, string $text = '<p>Q</p>', string $answer = 'B', array $extra = []): Question
    {
        $attrs = $extra + [
            'paper_id' => $paper->id,
            'question' => $text,
            'answer' => $answer,
            'options' => ['A' => 'one', 'B' => 'two', 'C' => 'three', 'D' => 'four'],
            'mark' => 1,
            'not_question' => 0,
        ];

        // The legacy column holds a JSON string (the Question model has no array cast).
        $attrs['options'] = json_encode($attrs['options']);

        return Question::create($attrs);
    }
}
