<?php

namespace Tests\Feature;

use App\Models\Paper;
use App\Models\Topic;
use App\Services\QuestionSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class QuestionSelectorTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private function passage($paper, string $html = '<p>Read this.</p>')
    {
        return $this->makeQuestion($paper, $html, '', ['not_question' => 1, 'options' => ['A' => null]]);
    }

    public function test_only_published_papers_with_a_usable_answer_key_are_offered(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $live = $this->makePaper($exam, $subject, 2020);
        $draft = $this->makePaper($exam, $subject, 2021, Paper::DRAFT);
        $good = $this->makeQuestion($live, '<p>Good</p>', 'B');
        $this->makeQuestion($live, '<p>No key</p>', '');
        $this->makeQuestion($live, '<p>Key not an option</p>', 'E');
        $this->makeQuestion($draft, '<p>Draft</p>', 'B');

        $r = app(QuestionSelector::class)->practice($exam, $subject, null, 50);

        $this->assertSame([$good->id], array_column($r['questions'], 'id'));
        $this->assertSame('B', $r['questions'][0]['answer']);
        $this->assertSame(['A' => 'one', 'B' => 'two', 'C' => 'three', 'D' => 'four'], $r['questions'][0]['options']);
    }

    public function test_choosing_a_year_keeps_exam_order_and_all_years_mixes_them(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $p2019 = $this->makePaper($exam, $subject, 2019);
        $p2020 = $this->makePaper($exam, $subject, 2020);
        $ids2019 = collect(range(1, 12))->map(fn ($i) => $this->makeQuestion($p2019, "<p>A$i</p>")->id)->all();
        collect(range(1, 12))->each(fn ($i) => $this->makeQuestion($p2020, "<p>B$i</p>"));

        $selector = app(QuestionSelector::class);

        $one = $selector->practice($exam, $subject, 2019, 5);
        $this->assertSame(array_slice($ids2019, 0, 5), array_column($one['questions'], 'id'), 'first five, in order');

        $mixed = $selector->practice($exam, $subject, null, 24, null, 42);
        $this->assertCount(24, $mixed['questions']);
        $this->assertCount(24, array_unique(array_column($mixed['questions'], 'id')));
        $this->assertNotSame(array_column($mixed['questions'], 'id'), array_merge($ids2019, range(13, 24)), 'shuffled');
        $this->assertEqualsCanonicalizing([2019, 2020], array_unique(array_column($mixed['questions'], 'year')));
    }

    public function test_a_passage_stays_attached_to_its_questions_and_is_never_split_by_shuffling(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $passage = $this->passage($paper, '<p>The long story.</p>');
        $group = collect(range(1, 3))->map(fn ($i) => $this->makeQuestion($paper, "<p>About story $i</p>")->id)->all();
        // Questions with no passage live in another paper (anything after a passage row belongs to it).
        $other = $this->makePaper($exam, $subject, 2018);
        collect(range(1, 8))->each(fn ($i) => $this->makeQuestion($other, "<p>Stand-alone $i</p>"));

        for ($seed = 1; $seed <= 12; $seed++) {
            $r = app(QuestionSelector::class)->practice($exam, $subject, null, 100, null, $seed);
            $ids = array_column($r['questions'], 'id');

            $pos = array_map(fn ($id) => array_search($id, $ids), $group);
            $this->assertSame(range(min($pos), min($pos) + 2), $pos, "passage group contiguous with seed $seed");
        }

        $r = app(QuestionSelector::class)->practice($exam, $subject, null, 100, null, 1);
        $byId = collect($r['questions'])->keyBy('id');
        $this->assertSame($passage->id, $byId[$group[0]]['passage_id']);
        $this->assertStringContainsString('The long story.', $r['passages'][$passage->id]);
    }

    public function test_general_directions_covering_many_questions_are_not_treated_as_a_passage(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $this->passage($paper, '<p>Answer all questions.</p>');
        collect(range(1, QuestionSelector::PASSAGE_MAX_QUESTIONS + 1))->each(fn ($i) => $this->makeQuestion($paper, "<p>Q$i</p>"));

        $r = app(QuestionSelector::class)->practice($exam, $subject, 2019, 50);

        $this->assertSame([], $r['passages']);
        $this->assertSame([null], array_unique(array_column($r['questions'], 'passage_id')));
    }

    public function test_a_passage_does_not_leak_into_the_next_paper(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $first = $this->makePaper($exam, $subject, 2019);
        $this->passage($first);
        $this->makeQuestion($first, '<p>Under passage</p>');
        $second = $this->makePaper($exam, $subject, 2020);
        $orphan = $this->makeQuestion($second, '<p>Fresh paper</p>');

        $r = app(QuestionSelector::class)->practice($exam, $subject, 2020, 5);

        $this->assertNull($r['questions'][0]['passage_id']);
        $this->assertSame($orphan->id, $r['questions'][0]['id']);
    }

    public function test_topic_filter_and_labels(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject, 2022);
        $topic = Topic::create(['subject_id' => $subject->id, 'name' => 'Concord', 'slug' => 'concord']);
        $tagged = $this->makeQuestion($paper, '<p>T</p>', 'B', ['topic_id' => $topic->id, 'explanation_en' => '<p>Because.</p>', 'explanation_pcm' => 'Na so.']);
        $this->makeQuestion($paper, '<p>Untagged</p>');

        $r = app(QuestionSelector::class)->practice($exam, $subject, null, 10, $topic->id);

        $this->assertSame([$tagged->id], array_column($r['questions'], 'id'));
        $this->assertSame('Concord', $r['questions'][0]['topic']);
        $this->assertSame(2022, $r['questions'][0]['year']);
        $this->assertSame('<p>Because.</p>', $r['questions'][0]['explanation_en']);
        $this->assertSame('Na so.', $r['questions'][0]['explanation_pcm']);
    }

    public function test_question_html_is_sanitised_before_it_reaches_a_student(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $this->makeQuestion($paper, '<p onclick="x()">Hi</p><script>alert(1)</script>', 'B', [
            'options' => ['A' => '<b>one</b><img src=x onerror=alert(1)>', 'B' => 'two'],
            'explanation_en' => '<iframe src="//evil.test"></iframe>Because.',
        ]);

        $q = app(QuestionSelector::class)->practice($exam, $subject, null, 5)['questions'][0];
        $all = json_encode($q);

        foreach (['onclick', 'onerror', '<script', 'alert', 'iframe', 'evil'] as $bad) {
            $this->assertStringNotContainsString($bad, $all);
        }
        $this->assertStringContainsString('Hi', $q['html']);
        $this->assertStringContainsString('Because.', $q['explanation_en']);
    }

    public function test_saved_selection_only_offers_this_students_current_bookmarks(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $a = $this->makeQuestion($paper, '<p>A</p>');
        $b = $this->makeQuestion($paper, '<p>B</p>');
        $c = $this->makeQuestion($paper, '<p>C</p>');
        $me = $this->makeUser();
        $other = $this->makeUser();

        $row = fn ($u, $q, $on) => ['user_id' => $u->id, 'question_id' => $q->id, 'is_bookmarked' => $on, 'changed_at' => now(), 'created_at' => now(), 'updated_at' => now()];
        DB::table('bookmarks')->insert([$row($me, $a, true), $row($me, $b, false), $row($other, $c, true)]);

        $r = app(QuestionSelector::class)->saved($me, 20);

        $this->assertSame([$a->id], array_column($r['questions'], 'id'));
        $this->assertSame([], app(QuestionSelector::class)->saved($this->makeUser(), 20)['questions']);
    }

    public function test_availability_and_years(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $p19 = $this->makePaper($exam, $subject, 2019);
        $p21 = $this->makePaper($exam, $subject, 2021);
        $this->makePaper($exam, $subject, 2022, Paper::DRAFT);
        $this->makeQuestion($p19);
        $this->makeQuestion($p19);
        $this->passage($p21);
        $this->makeQuestion($p21);

        $selector = app(QuestionSelector::class);

        $this->assertSame(3, $selector->availability($exam)[$subject->id], 'passages are not questions');
        $this->assertSame([2021, 2019], $selector->years($exam, $subject)->all());
    }
}
