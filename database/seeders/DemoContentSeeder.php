<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Made-up questions so a developer can try Practice, Mock and the console without real content.
 * Every question is labelled [Demo]. Refuses to run in production.
 */
class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('DemoContentSeeder must never run in production.');
            return;
        }

        $this->call(ExamCatalogSeeder::class);

        $plan = [
            'jamb' => ['english-language' => 65, 'mathematics' => 45, 'physics' => 45, 'chemistry' => 45, 'biology' => 45],
            'waec' => ['mathematics' => 30, 'physics' => 30, 'english-language' => 30],
        ];

        foreach ($plan as $examSlug => $subjects) {
            $exam = Exam::where('slug', $examSlug)->firstOrFail();

            foreach ($subjects as $subjectSlug => $count) {
                $subject = Subject::where('slug', $subjectSlug)->firstOrFail();
                $topics = collect(['Basics', 'Applications', 'Problem solving'])->map(fn ($name) => Topic::firstOrCreate(
                    ['subject_id' => $subject->id, 'slug' => Str::slug($name)], ['name' => $name]
                ));

                $paper = Paper::firstOrCreate(
                    ['exam_id' => $exam->id, 'subject_id' => $subject->id, 'year' => 2023, 'title' => "[Demo] {$exam->name} {$subject->name} 2023"],
                    ['status' => Paper::PUBLISHED, 'published_at' => now(), 'duration_minutes' => 60]
                );

                if ($paper->questions()->exists()) { continue; }

                for ($i = 1; $i <= $count; $i++) {
                    $a = ($i * 7) % 23 + 2;
                    $b = ($i * 5) % 17 + 3;
                    $right = $a + $b;
                    $letters = ['A', 'B', 'C', 'D'];
                    $answer = $letters[$i % 4];
                    $options = [];
                    $wrong = [$right + 1, $right - 2, $right + 4];
                    foreach ($letters as $letter) {
                        $options[$letter] = (string) ($letter === $answer ? $right : array_shift($wrong));
                    }

                    Question::create([
                        'paper_id' => $paper->id,
                        'topic_id' => $topics[$i % 3]->id,
                        'question' => "<p>[Demo] {$subject->name} question {$i}: what is {$a} + {$b}?</p>",
                        'options' => json_encode($options),
                        'answer' => $answer,
                        'mark' => 1,
                        'not_question' => 0,
                        'explanation_en' => "Add {$a} and {$b} to get {$right}.",
                        'explanation_pcm' => "Just add {$a} and {$b}, na {$right} you go get.",
                    ]);
                }
            }
        }
    }
}
