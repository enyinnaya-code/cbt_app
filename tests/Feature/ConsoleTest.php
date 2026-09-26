<?php

namespace Tests\Feature;

use App\Models\ContentPack;
use App\Models\Exam;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class ConsoleTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private User $admin;
    private User $examiner;
    private User $student;
    private Exam $exam;
    private Subject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = $this->makeUser(2);
        $this->examiner = $this->makeUser(3);
        $this->student = $this->makeUser(4);
        [$this->exam, $this->subject] = $this->makeExamAndSubject('WAEC', 'Physics');
    }

    private function draft(int $year = 2019): Paper
    {
        return $this->makePaper($this->exam, $this->subject, $year, Paper::DRAFT);
    }

    private function mcq(array $over = []): array
    {
        return $over + [
            'type' => 'question', 'question' => '<p>What is <b>force</b>?</p>',
            'options' => ['A' => 'Mass x acceleration', 'B' => 'Speed', 'C' => '', 'D' => '', 'E' => ''],
            'answer' => 'A', 'mark' => 2,
        ];
    }

    private function csv(string $body, string $header = 'type,question,option_a,option_b,option_c,option_d,option_e,answer,marks,topic,explanation_en,explanation_pcm'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $header . "\n" . $body);

        return new UploadedFile($path, 'questions.csv', 'text/csv', null, true);
    }

    // ================================================================ access

    public function test_only_staff_can_open_the_console(): void
    {
        $urls = ['/console/papers', '/console/papers/create', '/console/topics', '/console/packs'];

        // Guests first: actingAs stays in effect for the rest of a test.
        foreach ($urls as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        foreach ($urls as $url) {
            $this->actingAs($this->student)->get($url)->assertForbidden();
            $this->actingAs($this->examiner)->get($url)->assertOk();
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_admin_only_pages_and_actions_are_closed_to_examiners(): void
    {
        $paper = $this->draft();

        foreach (['/console/users', '/console/legacy'] as $url) {
            $this->actingAs($this->examiner)->get($url)->assertForbidden();
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
        $this->actingAs($this->examiner)->post("/console/papers/{$paper->id}/publish")->assertForbidden();
        $this->actingAs($this->examiner)->post('/console/packs/rebuild')->assertForbidden();
        $this->actingAs($this->examiner)->patch("/console/users/{$this->student->id}/toggle")->assertForbidden();
        $this->assertSame('draft', $paper->fresh()->status);
    }

    public function test_staff_land_on_the_console_overview_and_students_on_home(): void
    {
        $this->actingAs($this->admin)->get('/dashboard')->assertOk()->assertSee('What students can practise');
        $this->actingAs($this->examiner)->get('/dashboard')->assertOk()->assertSee('What students can practise');
        $this->actingAs($this->student)->get('/dashboard')->assertOk()->assertDontSee('What students can practise');
    }

    // ================================================================ papers

    public function test_creating_a_paper_makes_a_draft_owned_by_its_author(): void
    {
        $r = $this->actingAs($this->examiner)->post('/console/papers', [
            'exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'year' => 2018, 'title' => '', 'duration_minutes' => 60,
        ]);

        $paper = Paper::firstOrFail();
        $r->assertRedirect(route('console.papers.show', $paper));
        $this->assertSame('draft', $paper->status);
        $this->assertSame($this->examiner->id, $paper->created_by);
        $this->assertSame(2018, $paper->year);
        $this->assertNull($paper->title, 'a blank title stays empty and the label is generated');
    }

    public function test_a_paper_needs_a_real_year_and_a_subject_that_exam_offers(): void
    {
        [$neco] = $this->makeExamAndSubject('NECO', 'Biology');
        $base = ['exam_id' => $neco->id, 'subject_id' => $this->subject->id, 'year' => 2018];

        $this->actingAs($this->admin)->post('/console/papers', $base)->assertSessionHasErrors('subject_id');
        $this->actingAs($this->admin)->post('/console/papers', ['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'year' => 1800])->assertSessionHasErrors('year');
        $this->actingAs($this->admin)->post('/console/papers', ['exam_id' => 999, 'subject_id' => $this->subject->id, 'year' => 2018])->assertSessionHasErrors('exam_id');
        $this->assertSame(0, Paper::count());
    }

    public function test_the_list_filters_and_paginates(): void
    {
        [, $chem] = $this->makeExamAndSubject('WAEC', 'Chemistry');
        $this->draft(2019);
        $this->makePaper($this->exam, $chem, 2020);
        foreach (range(1, 22) as $i) { $this->makePaper($this->exam, $this->subject, 1990 + $i, Paper::DRAFT); }

        $this->actingAs($this->admin)->get('/console/papers?status=published')->assertOk()->assertSee('WAEC Chemistry 2020')->assertDontSee('WAEC Physics 2019');
        $this->actingAs($this->admin)->get('/console/papers?subject=' . $chem->id)->assertSee('WAEC Chemistry 2020')->assertDontSee('WAEC Physics 2019');
        $this->actingAs($this->admin)->get('/console/papers?year=1995')->assertSee('WAEC Physics 1995')->assertDontSee('WAEC Physics 2019');
        $this->actingAs($this->admin)->get('/console/papers')->assertSee('Next')->assertSee('Last');
        $this->actingAs($this->admin)->get('/console/papers?status=nonsense')->assertSessionHasErrors('status');
    }

    public function test_examiners_edit_drafts_but_not_live_papers(): void
    {
        $draft = $this->draft();
        $live = $this->makePaper($this->exam, $this->subject, 2020);
        $q = $this->makeQuestion($live, '<p>Live</p>');
        $body = ['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'year' => 2001];

        $this->actingAs($this->examiner)->put("/console/papers/{$draft->id}", $body)->assertRedirect();
        $this->assertSame(2001, $draft->fresh()->year);

        $this->actingAs($this->examiner)->put("/console/papers/{$live->id}", $body)->assertForbidden();
        $this->actingAs($this->examiner)->get("/console/papers/{$live->id}/questions/create")->assertForbidden();
        $this->actingAs($this->examiner)->get("/console/questions/{$q->id}/edit")->assertForbidden();
        $this->actingAs($this->examiner)->delete("/console/questions/{$q->id}")->assertForbidden();
        $this->actingAs($this->examiner)->get("/console/papers/{$live->id}")->assertOk()->assertSee('only an admin can change it');

        $this->actingAs($this->admin)->put("/console/papers/{$live->id}", ['year' => 2002] + $body)->assertRedirect();
        $this->assertSame(2002, $live->fresh()->year);
    }

    public function test_publishing_needs_a_usable_question_and_builds_the_pack(): void
    {
        $paper = $this->draft();

        $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/publish")->assertSessionHas('error');
        $this->assertSame('draft', $paper->fresh()->status);

        $this->makeQuestion($paper, '<p>Q</p>', 'B');
        $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/publish")->assertSessionHas('success');

        $this->assertSame('published', $paper->fresh()->status);
        $this->assertNotNull($paper->fresh()->published_at);
        $pack = ContentPack::current()->firstOrFail();
        $this->assertSame(1, $pack->question_count);
        Storage::disk('local')->assertExists($pack->path);
    }

    public function test_unpublishing_takes_the_pack_away(): void
    {
        $paper = $this->draft();
        $this->makeQuestion($paper);
        $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/publish");
        $this->assertSame(1, ContentPack::current()->count());

        $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/unpublish")->assertSessionHas('success');

        $this->assertSame('draft', $paper->fresh()->status);
        $this->assertSame(0, ContentPack::current()->count());
    }

    public function test_moving_a_live_paper_to_another_subject_updates_both_packs(): void
    {
        [, $chem] = $this->makeExamAndSubject('WAEC', 'Chemistry');
        $paper = $this->draft();
        $this->makeQuestion($paper);
        $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/publish");

        $this->actingAs($this->admin)->put("/console/papers/{$paper->id}", ['exam_id' => $this->exam->id, 'subject_id' => $chem->id, 'year' => 2019]);

        $current = ContentPack::current()->get();
        $this->assertSame([$chem->id], $current->pluck('subject_id')->all(), 'the physics pack was retired and a chemistry pack built');
    }

    public function test_deleting_a_paper_has_guards(): void
    {
        $live = $this->makePaper($this->exam, $this->subject, 2020);
        $answered = $this->draft(2018);
        $q = $this->makeQuestion($answered);
        DB::table('question_attempts')->insert(['user_id' => $this->student->id, 'client_uuid' => (string) Str::uuid(), 'question_id' => $q->id, 'mode' => 'practice', 'selected' => 'B',
            'is_correct' => true, 'answered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $empty = $this->draft(2017);
        $this->makeQuestion($empty);

        $this->actingAs($this->admin)->delete("/console/papers/{$live->id}")->assertSessionHas('error');
        $this->actingAs($this->admin)->delete("/console/papers/{$answered->id}")->assertSessionHas('error');
        $this->assertSame(2, Paper::whereIn('id', [$live->id, $answered->id])->count());

        $this->actingAs($this->examiner)->delete("/console/papers/{$empty->id}")->assertRedirect(route('console.papers.index'));
        $this->assertNull(Paper::find($empty->id));
        $this->assertSame(0, Question::where('paper_id', $empty->id)->count(), 'its questions went with it');
    }

    // ================================================================ questions

    public function test_a_question_is_saved_with_sanitised_html_and_json_options(): void
    {
        $paper = $this->draft();

        $this->actingAs($this->examiner)->post("/console/papers/{$paper->id}/questions", $this->mcq([
            'question' => '<p onclick="x()">Force?</p><script>alert(1)</script>',
            'options' => ['A' => '<b>Mass</b><img src=x onerror=alert(1)>', 'B' => 'Speed', 'C' => '', 'D' => '', 'E' => ''],
            'explanation_en' => '<iframe src="//evil.test"></iframe>Because F = ma.', 'explanation_pcm' => '   ',
        ]))->assertRedirect();

        $q = Question::firstOrFail();
        $this->assertSame($paper->id, $q->paper_id);
        $this->assertSame(0, (int) $q->not_question);
        $this->assertSame(2, $q->mark);
        $this->assertSame('A', $q->answer);
        $this->assertNull($q->explanation_pcm, 'blank explanations are stored as nothing');
        $all = $q->question . $q->options . $q->explanation_en;
        foreach (['onclick', 'onerror', '<script', 'alert', 'iframe', 'evil'] as $bad) { $this->assertStringNotContainsString($bad, $all); }
        $this->assertSame(['A', 'B'], array_keys(json_decode($q->options, true)), 'empty options are left out');
        $this->assertStringContainsString('Because F = ma.', $q->explanation_en);
    }

    public function test_question_validation(): void
    {
        $paper = $this->draft();
        $post = fn (array $over) => $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/questions", $this->mcq($over));

        $post(['question' => '   '])->assertSessionHasErrors('question');
        $post(['question' => '<p>&nbsp;</p>'])->assertSessionHasErrors('question');
        $post(['options' => ['A' => 'Only one', 'B' => '', 'C' => '', 'D' => '', 'E' => '']])->assertSessionHasErrors('options');
        $post(['answer' => 'C'])->assertSessionHasErrors('answer');        // C was left blank
        $post(['answer' => 'Z'])->assertSessionHasErrors('answer');
        $post(['mark' => 99])->assertSessionHasErrors('mark');

        [, $other] = $this->makeExamAndSubject('WAEC', 'Biology');
        $foreignTopic = Topic::create(['subject_id' => $other->id, 'name' => 'Cells', 'slug' => 'cells']);
        $post(['topic_id' => $foreignTopic->id])->assertSessionHasErrors('topic_id');

        $this->assertSame(0, Question::count());
    }

    public function test_an_image_only_option_counts_as_an_answer(): void
    {
        $paper = $this->draft();

        $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/questions", $this->mcq([
            'options' => ['A' => '<img src="/uploads/a.png">', 'B' => '<img src="/uploads/b.png">', 'C' => '', 'D' => '', 'E' => ''], 'answer' => 'B',
        ]))->assertSessionHasNoErrors();

        $this->assertSame(1, Question::count());
    }

    public function test_a_passage_needs_only_text_and_has_no_answer(): void
    {
        $paper = $this->draft();

        $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/questions", ['type' => 'passage', 'question' => '<p>Read carefully.</p>'])->assertSessionHasNoErrors();

        $q = Question::firstOrFail();
        $this->assertSame(1, (int) $q->not_question);
        $this->assertNull($q->answer);
        $this->assertNull($q->options);
    }

    public function test_editing_and_deleting_a_question(): void
    {
        $paper = $this->draft();
        $q = $this->makeQuestion($paper, '<p>Old</p>', 'B');

        $this->actingAs($this->examiner)->get("/console/questions/{$q->id}/edit")->assertOk()->assertSee('Old', false);
        $this->actingAs($this->examiner)->put("/console/questions/{$q->id}", $this->mcq(['question' => '<p>New wording</p>', 'answer' => 'B']))->assertRedirect();
        $this->assertStringContainsString('New wording', $q->fresh()->question);

        DB::table('question_attempts')->insert(['user_id' => $this->student->id, 'client_uuid' => (string) Str::uuid(), 'question_id' => $q->id, 'mode' => 'practice', 'selected' => 'A',
            'is_correct' => false, 'answered_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->examiner)->delete("/console/questions/{$q->id}")->assertSessionHas('success', fn ($m) => str_contains($m, '1 student answer'));
        $this->assertNull(Question::find($q->id));
        $this->assertSame(0, DB::table('question_attempts')->count());
    }

    public function test_save_and_add_another_returns_to_a_blank_form(): void
    {
        $paper = $this->draft();

        $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/questions", $this->mcq() + ['add_another' => 1])
            ->assertRedirect(route('console.questions.create', [$paper, 'type' => 'question']));
    }

    public function test_old_school_questions_without_a_paper_cannot_be_edited_here(): void
    {
        $orphan = Question::create(['question' => '<p>Legacy</p>', 'answer' => 'A', 'options' => '{"A":"x","B":"y"}', 'mark' => 1, 'not_question' => 0]);

        $this->actingAs($this->admin)->get("/console/questions/{$orphan->id}/edit")->assertNotFound();
    }

    public function test_the_paper_page_shows_items_in_order_with_warnings(): void
    {
        $paper = $this->draft();
        $this->makeQuestion($paper, '<p>Passage text</p>', '', ['not_question' => 1, 'options' => null]);
        $this->makeQuestion($paper, '<p>First real</p>', 'B');
        $this->makeQuestion($paper, '<p>Broken key</p>', 'E');

        $this->actingAs($this->admin)->get("/console/papers/{$paper->id}")->assertOk()
            ->assertSeeInOrder(['Passage text', 'First real', 'Broken key'])->assertSee('no explanation')->assertSee('no valid answer')->assertSee('2 questions');
    }

    // ================================================================ CSV import

    public function test_a_valid_csv_adds_questions_passages_topics_and_explanations(): void
    {
        $paper = $this->draft();
        $topic = Topic::create(['subject_id' => $this->subject->id, 'name' => 'Motion', 'slug' => 'motion']);

        $file = $this->csv(implode("\n", [
            'passage,"Read this. It has, a comma.",,,,,,,,,,',
            'question,"What is speed?",Distance/time,Mass,Force,,,a,3,motion,"Speed is distance over time.","Na distance divide time."',
            'question,x < 5 means?,less than,greater than,,,,A,,,,',
            '<p>HTML question</p>,,,,,,,,,,,',   // malformed on purpose? no: see below
        ]));
        // Replace the last deliberately odd row with a valid HTML one.
        file_put_contents($file->getPathname(), preg_replace('/\n<p>HTML question.*$/s', "\nquestion,<p>Is <b>this</b> bold?</p>,Yes,No,,,,A,1,,,\n", file_get_contents($file->getPathname())));

        $this->actingAs($this->examiner)->post("/console/papers/{$paper->id}/import", ['file' => $file])
            ->assertRedirect(route('console.papers.show', $paper))->assertSessionHas('success', 'Imported 3 questions.');

        $rows = $paper->questions()->orderBy('id')->get();
        $this->assertCount(4, $rows);
        $this->assertSame(1, (int) $rows[0]->not_question);
        $this->assertStringContainsString('a comma', $rows[0]->question);
        $this->assertSame([$topic->id, 3, 'A'], [$rows[1]->topic_id, $rows[1]->mark, $rows[1]->answer]);
        $this->assertSame('<p>Speed is distance over time.</p>', $rows[1]->explanation_en);
        $this->assertStringContainsString('x &lt; 5', $rows[2]->question, 'plain text with < is escaped, not mistaken for a tag');
        $this->assertStringContainsString('<b>this</b>', $rows[3]->question, 'a cell starting with a tag is kept as HTML');
        $this->assertSame(['A', 'B'], array_keys(json_decode($rows[2]->options, true)));
    }

    public function test_one_bad_row_imports_nothing_and_every_problem_is_listed_with_its_line(): void
    {
        $paper = $this->draft();

        $file = $this->csv(implode("\n", [
            'question,Fine one,a,b,,,,A,,,,',
            'question,,a,b,,,,A,,,,',                 // line 3: empty text
            'question,One option,a,,,,,A,,,,',        // line 4: too few options
            'question,Bad key,a,b,,,,C,,,,',          // line 5: answer not filled in
            'question,Bad marks,a,b,,,,A,abc,,,',     // line 6
            'question,Bad topic,a,b,,,,A,,Nowhere,,', // line 7
            'poem,Bad type,a,b,,,,A,,,,',             // line 8
        ]));

        $r = $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/import", ['file' => $file]);

        $r->assertRedirect()->assertSessionHas('import_errors', function ($errors) {
            return array_keys($errors) === [3, 4, 5, 6, 7, 8]
                && str_contains($errors[3], 'empty') && str_contains($errors[4], 'two options') && str_contains($errors[5], 'not one of')
                && str_contains($errors[6], 'Marks') && str_contains($errors[7], 'Unknown topic') && str_contains($errors[8], 'Unknown type');
        });
        $this->assertSame(0, Question::count(), 'the valid first row was not saved either');
    }

    public function test_the_file_must_look_right(): void
    {
        $paper = $this->draft();
        $post = fn ($file) => $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/import", ['file' => $file]);

        $post($this->csv('', 'question,option_a'))->assertSessionHas('import_errors', fn ($e) => str_contains($e[1], 'option_b'));
        $post($this->csv('', 'question,option_a,option_b,answer'))->assertSessionHas('import_errors', fn ($e) => str_contains($e[1], 'no questions'));
        $post(UploadedFile::fake()->create('macro.exe', 10))->assertSessionHasErrors('file');
        $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/import", [])->assertSessionHasErrors('file');
        $this->assertSame(0, Question::count());
    }

    public function test_excel_style_files_keep_their_accents_and_a_bom_is_ignored(): void
    {
        $paper = $this->draft();
        $body = "question,option_a,option_b,answer\n" . mb_convert_encoding("Ọ̀gbẹ́ni costs ₦500 at café?,Yes,No,A\n", 'Windows-1252', 'UTF-8');
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, "\xEF\xBB\xBF" . $body);

        $this->actingAs($this->admin)->post("/console/papers/{$paper->id}/import", ['file' => new UploadedFile($path, 'q.csv', 'text/csv', null, true)])->assertSessionHas('success');

        $this->assertStringContainsString('café', Question::firstOrFail()->question);
    }

    public function test_the_sample_file_downloads_and_imports_cleanly(): void
    {
        $paper = $this->draft();
        Topic::create(['subject_id' => $this->subject->id, 'name' => 'Vocabulary', 'slug' => 'vocabulary']);

        $r = $this->actingAs($this->examiner)->get('/console/import/sample')->assertOk();
        $this->assertStringContainsString('text/csv', $r->headers->get('Content-Type'));

        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $r->getContent());
        $this->actingAs($this->examiner)->post("/console/papers/{$paper->id}/import", ['file' => new UploadedFile($path, 's.csv', 'text/csv', null, true)])->assertSessionHas('success');
        $this->assertSame(3, $paper->questions()->count());
    }

    public function test_importing_into_a_live_paper_is_refused_for_examiners(): void
    {
        $live = $this->makePaper($this->exam, $this->subject, 2020);

        $this->actingAs($this->examiner)->get("/console/papers/{$live->id}/import")->assertForbidden();
        $this->actingAs($this->examiner)->post("/console/papers/{$live->id}/import", ['file' => $this->csv('question,a,b,,,,A,,,,', 'question,option_a,option_b,answer')])->assertForbidden();
    }

    // ================================================================ topics

    public function test_topics_can_be_added_in_bulk_without_duplicates(): void
    {
        $this->actingAs($this->examiner)->post('/console/topics', ['subject_id' => $this->subject->id, 'names' => "Motion\n  Heat   and light \n\nmotion\nMOTION"])
            ->assertSessionHas('success', 'Added 2 topics.');

        $this->assertSame(['Heat and light', 'Motion'], Topic::orderBy('name')->pluck('name')->all());
        $this->actingAs($this->examiner)->post('/console/topics', ['subject_id' => $this->subject->id, 'names' => 'Motion'])->assertSessionHas('error');
        $this->actingAs($this->examiner)->get('/console/topics?subject=' . $this->subject->id)->assertOk()->assertSee('Motion')->assertSee('Heat and light');
    }

    public function test_renaming_refuses_a_clash_and_deleting_keeps_the_questions(): void
    {
        $motion = Topic::create(['subject_id' => $this->subject->id, 'name' => 'Motion', 'slug' => 'motion']);
        $heat = Topic::create(['subject_id' => $this->subject->id, 'name' => 'Heat', 'slug' => 'heat']);
        $q = $this->makeQuestion($this->draft(), '<p>Q</p>', 'B', ['topic_id' => $motion->id]);

        $this->actingAs($this->admin)->put("/console/topics/{$heat->id}", ['name' => 'motion'])->assertSessionHas('error');
        $this->actingAs($this->admin)->put("/console/topics/{$heat->id}", ['name' => 'Thermal physics'])->assertSessionHas('success');
        $this->assertSame('Thermal physics', $heat->fresh()->name);

        $this->actingAs($this->admin)->delete("/console/topics/{$motion->id}")->assertSessionHas('success');
        $this->assertNull($q->fresh()->topic_id);
        $this->assertNotNull(Question::find($q->id));
    }

    // ================================================================ packs

    public function test_the_packs_page_shows_state_and_only_admins_can_rebuild(): void
    {
        $paper = $this->draft();
        $this->makeQuestion($paper);
        $paper->update(['status' => 'published']);

        $this->actingAs($this->examiner)->get('/console/packs')->assertOk()->assertSee('not built yet');

        $this->actingAs($this->admin)->post('/console/packs/rebuild')->assertSessionHas('success', fn ($m) => str_starts_with($m, '1 pack(s) updated'));
        $this->actingAs($this->admin)->get('/console/packs')->assertOk()->assertSee('v1')->assertDontSee('not built yet');

        // Editing a live paper makes the pack stale until it is rebuilt.
        $paper->touch();
        DB::table('papers')->where('id', $paper->id)->update(['updated_at' => now()->addMinute()]);
        $this->actingAs($this->admin)->get('/console/packs')->assertSee('out of date');

        $this->actingAs($this->admin)->post('/console/packs/rebuild', ['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id])->assertSessionHas('success');
    }

    // ================================================================ old tests

    private function legacyTest(int $questions = 2): int
    {
        DB::table('sections')->insertOrIgnore(['id' => 1, 'section_name' => 'S', 'created_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('courses')->insertOrIgnore(['id' => 1, 'course_name' => 'C', 'section_id' => 1, 'added_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('school_classes')->insertOrIgnore(['id' => 1, 'name' => 'K', 'section_id' => 1, 'added_by' => $this->admin->id, 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('tests')->insertGetId(['test_name' => 'WAEC GOVERNMENT', 'created_by' => $this->admin->id, 'section_id' => 1, 'course_id' => 1, 'class_id' => 1,
            'test_type' => 'multiple_choice', 'duration' => 45, 'pass_mark' => 5, 'created_at' => now(), 'updated_at' => now()]);
        foreach (range(1, $questions) as $i) { Question::create(['test_id' => $id, 'question' => "<p>L$i</p>", 'answer' => 'A', 'options' => '{"A":"x","B":"y"}', 'mark' => 1, 'not_question' => 0]); }

        return $id;
    }

    public function test_an_old_test_becomes_a_draft_paper_with_its_questions(): void
    {
        $id = $this->legacyTest(3);

        $this->actingAs($this->admin)->get('/console/legacy')->assertOk()->assertSee('WAEC GOVERNMENT')->assertSee('3 questions');
        $this->actingAs($this->admin)->get('/dashboard')->assertSee('1 old test still to organise');

        $r = $this->actingAs($this->admin)->post("/console/legacy/{$id}", ['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'year' => 2021]);

        $paper = Paper::firstOrFail();
        $r->assertRedirect(route('console.papers.show', $paper));
        $this->assertSame(['draft', 2021, $id, 45], [$paper->status, $paper->year, $paper->legacy_test_id, $paper->duration_minutes]);
        $this->assertSame(3, $paper->questions()->count());
        $this->actingAs($this->admin)->get('/console/legacy')->assertSee('All done');

        // Doing it again changes nothing.
        $this->actingAs($this->admin)->post("/console/legacy/{$id}", ['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'year' => 2021]);
        $this->assertSame(1, Paper::count());
    }

    public function test_an_old_test_with_no_questions_is_not_turned_into_a_paper(): void
    {
        $id = $this->legacyTest(1);
        Question::where('test_id', $id)->delete();

        $this->actingAs($this->admin)->post("/console/legacy/{$id}", ['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'year' => 2020])
            ->assertSessionHas('error');
        $this->assertSame(0, Paper::count());
    }

    public function test_tagging_an_old_test_validates_the_year_and_needs_admin(): void
    {
        $id = $this->legacyTest();

        $this->actingAs($this->admin)->post("/console/legacy/{$id}", ['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'year' => 1500])->assertSessionHasErrors('year');
        $this->actingAs($this->examiner)->post("/console/legacy/{$id}", ['exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'year' => 2020])->assertForbidden();
        $this->assertSame(0, Paper::count());
    }

    // ================================================================ users

    public function test_the_user_list_searches_and_filters(): void
    {
        $this->makeUser(4, ['name' => 'Chidinma Okafor', 'email' => 'chi@example.com']);
        $this->makeUser(4, ['name' => 'Bola Ade', 'email' => 'bola@example.com', 'is_active' => 0]);

        $this->actingAs($this->admin)->get('/console/users?q=chidi')->assertSee('Chidinma')->assertDontSee('Bola Ade');
        $this->actingAs($this->admin)->get('/console/users?state=suspended')->assertSee('Bola Ade')->assertDontSee('Chidinma');
        $this->actingAs($this->admin)->get('/console/users?role=examiner')->assertSee($this->examiner->name)->assertDontSee('Chidinma');
    }

    public function test_an_admin_can_create_an_examiner_but_not_a_duplicate(): void
    {
        $body = ['name' => 'Ife Teacher', 'email' => 'Ife@Example.com', 'role' => 'examiner', 'password' => 'temp-pass-1'];

        $this->actingAs($this->admin)->post('/console/users', $body)->assertSessionHas('success');

        $u = User::where('email', 'ife@example.com')->firstOrFail();
        $this->assertSame('examiner', $u->role);
        $this->assertSame(3, (int) $u->user_type);
        $this->assertSame($this->admin->id, (int) $u->added_by);
        $this->assertTrue(\Hash::check('temp-pass-1', $u->password));

        $this->actingAs($this->admin)->post('/console/users', $body)->assertSessionHasErrors('email');
        $this->actingAs($this->admin)->post('/console/users', ['role' => 'student'] + $body + ['email' => 'x@example.com'])->assertSessionHasErrors('role');
    }

    public function test_changing_a_role_updates_permissions_and_signs_the_phone_out(): void
    {
        $this->student->createToken('phone');

        $this->actingAs($this->admin)->patch("/console/users/{$this->student->id}/role", ['role' => 'examiner'])->assertSessionHas('success');

        $u = $this->student->fresh();
        $this->assertSame(['examiner', 3], [$u->role, (int) $u->user_type]);
        $this->assertSame(0, $u->tokens()->count());
        $this->actingAs($u)->get('/console/papers')->assertOk();

        $this->actingAs($this->admin)->patch("/console/users/{$u->id}/role", ['role' => 'wizard'])->assertSessionHasErrors('role');
    }

    public function test_a_super_admin_stays_a_super_admin_when_saved_as_admin(): void
    {
        $super = $this->makeUser(1);

        $this->actingAs($this->admin)->patch("/console/users/{$super->id}/role", ['role' => 'admin']);

        $this->assertSame(1, (int) $super->fresh()->user_type);
    }

    public function test_admins_cannot_lock_themselves_or_everyone_out(): void
    {
        $this->actingAs($this->admin)->patch("/console/users/{$this->admin->id}/role", ['role' => 'student'])->assertSessionHas('error');
        $this->actingAs($this->admin)->patch("/console/users/{$this->admin->id}/toggle")->assertSessionHas('error');
        $this->assertSame('admin', $this->admin->fresh()->role);

        // With a second admin, the first may demote the second, but never the last remaining admin.
        $second = $this->makeUser(2);
        $this->actingAs($this->admin)->patch("/console/users/{$second->id}/role", ['role' => 'student'])->assertSessionHas('success');
        $this->assertSame('student', $second->fresh()->role);

        $only = $this->makeUser(2);
        $this->admin->update(['is_active' => 0]);   // the requester's own row does not count as an active admin
        $this->actingAs($only)->patch("/console/users/{$only->id}/toggle")->assertSessionHas('error');
    }

    public function test_suspending_signs_the_person_out_everywhere_and_reactivating_restores_access(): void
    {
        $this->student->createToken('phone');

        $this->actingAs($this->admin)->patch("/console/users/{$this->student->id}/toggle")->assertSessionHas('success');
        $this->assertSame(0, (int) $this->student->fresh()->is_active);
        $this->assertSame(0, $this->student->tokens()->count());
        $this->postJson('/api/v1/auth/login', ['email' => $this->student->email, 'password' => 'password123', 'device_name' => 'p'])->assertStatus(403);

        $this->actingAs($this->admin)->patch("/console/users/{$this->student->id}/toggle");
        $this->assertSame(1, (int) $this->student->fresh()->is_active);
        $this->postJson('/api/v1/auth/login', ['email' => $this->student->email, 'password' => 'password123', 'device_name' => 'p'])->assertOk();
    }
}
