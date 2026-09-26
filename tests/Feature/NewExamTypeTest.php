<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Order;
use App\Models\Subject;
use App\Models\User;
use App\Services\MockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** An admin can add an exam type such as IELTS or GRE, and it works everywhere without a developer. */
class NewExamTypeTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->admin = $this->makeUser(2, ['is_active' => 1]);
    }

    /** Creates the exam through the console, gives it a subject with one published paper of questions. */
    private function ielts(): array
    {
        $this->actingAs($this->admin)->post('/console/exams', ['name' => 'IELTS'])->assertRedirect();
        $exam = Exam::where('slug', 'ielts')->firstOrFail();

        $this->actingAs($this->admin)->post('/console/subjects', ['name' => 'Reading', 'code' => 'Rd', 'exam_id' => $exam->id])->assertRedirect();
        $reading = Subject::where('slug', 'reading')->firstOrFail();
        $exam->subjects()->updateExistingPivot($reading->id, ['price' => 0]);

        $paper = $this->makePaper($exam, $reading, 2025);
        foreach (range(1, 8) as $i) { $this->makeQuestion($paper, "<p>Reading question $i</p>"); }

        return [$exam, $reading];
    }

    private function save(Exam $exam, array $over = [], array $subjects = [])
    {
        $reading = $exam->subjects()->first();

        return $this->actingAs($this->admin)->put("/console/exams/{$exam->id}", $over + [
            'name' => $exam->name, 'is_active' => 1, 'subjects' => $subjects ?: [$reading->id => ['price' => 0]],
        ]);
    }

    public function test_a_new_exam_type_appears_for_students_everywhere(): void
    {
        [$exam] = $this->ielts();
        $student = $this->makeUser(4, ['is_active' => 1]);

        $this->get('/')->assertOk()->assertSee('IELTS')->assertSee('Pass IELTS');
        $this->actingAs($student)->get('/practice?exam=ielts')->assertOk()->assertSee('IELTS')->assertSee('Reading');
        $this->actingAs($student)->get('/mock?exam=ielts')->assertOk()->assertSee('IELTS');
        $this->get('/pricing?exam=ielts')->assertOk()->assertSee('Reading');
        $this->assertSame('ielts', $exam->slug);
    }

    public function test_a_new_exam_gets_a_standard_mock_until_the_admin_sets_one(): void
    {
        [$exam] = $this->ielts();

        $format = app(MockService::class)->format($exam);

        $this->assertSame(['label' => 'IELTS mock', 'subject_count' => 1, 'compulsory' => null, 'questions' => ['default' => 50], 'minutes' => 60, 'score_max' => 100], $format);
    }

    public function test_the_admin_sets_the_mock_format_and_the_mock_uses_it(): void
    {
        [$exam, $reading] = $this->ielts();

        $this->save($exam, ['mock_custom' => 1, 'mock_label' => 'IELTS Reading practice test', 'mock_subject_count' => 1, 'mock_questions' => 6, 'mock_minutes' => 20])->assertSessionHasNoErrors();

        $format = app(MockService::class)->format($exam->fresh());
        $this->assertSame('IELTS Reading practice test', $format['label']);
        $this->assertSame(['default' => 6], $format['questions']);
        $this->assertSame(20, $format['minutes']);
        $this->assertSame(100, $format['score_max']);

        $student = $this->makeUser(4, ['is_active' => 1]);
        $run = app(MockService::class)->start($student, $exam->fresh(), ['reading']);
        $this->assertCount(6, $run->question_ids);
        $this->assertSame(20, $run->minutes);
    }

    public function test_a_subject_can_have_its_own_question_count_and_one_can_be_compulsory(): void
    {
        [$exam, $reading] = $this->ielts();
        $this->actingAs($this->admin)->post('/console/subjects', ['name' => 'Writing', 'code' => 'Wr', 'exam_id' => $exam->id]);
        $writing = Subject::where('slug', 'writing')->firstOrFail();

        $this->save($exam, ['mock_custom' => 1, 'mock_subject_count' => 2, 'mock_questions' => 40, 'mock_minutes' => 90, 'mock_compulsory' => 'reading'],
            [$reading->id => ['price' => 0, 'mock_questions' => 60], $writing->id => ['price' => 0, 'mock_questions' => 40]])->assertSessionHasNoErrors();

        $format = $exam->fresh()->mock_format;
        $this->assertSame(['default' => 40, 'reading' => 60], $format['questions'], 'a count equal to the default is not stored twice');
        $this->assertSame('reading', $format['compulsory']);
        $this->assertSame(200, $format['score_max']);
        $this->assertSame(60, app(MockService::class)->plannedQuestions($format, 'reading'));
        $this->assertSame(40, app(MockService::class)->plannedQuestions($format, 'writing'));
    }

    public function test_a_compulsory_subject_must_belong_to_the_exam_and_a_single_subject_mock_has_none(): void
    {
        [$exam] = $this->ielts();

        $this->save($exam, ['mock_custom' => 1, 'mock_subject_count' => 2, 'mock_questions' => 20, 'mock_minutes' => 30, 'mock_compulsory' => 'physics']);
        $this->assertNull($exam->fresh()->mock_format['compulsory']);

        $this->save($exam, ['mock_custom' => 1, 'mock_subject_count' => 1, 'mock_questions' => 20, 'mock_minutes' => 30, 'mock_compulsory' => 'reading']);
        $this->assertNull($exam->fresh()->mock_format['compulsory']);
    }

    public function test_unticking_the_box_goes_back_to_the_standard_format(): void
    {
        [$exam] = $this->ielts();
        $this->save($exam, ['mock_custom' => 1, 'mock_subject_count' => 1, 'mock_questions' => 10, 'mock_minutes' => 15]);
        $this->assertNotNull($exam->fresh()->mock_format);

        $this->save($exam);

        $this->assertNull($exam->fresh()->mock_format);
        $this->assertSame('IELTS mock', app(MockService::class)->format($exam->fresh())['label']);
    }

    public function test_the_mock_settings_are_checked(): void
    {
        [$exam] = $this->ielts();

        $this->save($exam, ['mock_custom' => 1])->assertSessionHasErrors(['mock_subject_count', 'mock_questions', 'mock_minutes']);
        $this->save($exam, ['mock_custom' => 1, 'mock_subject_count' => 9, 'mock_questions' => 2, 'mock_minutes' => 1])->assertSessionHasErrors(['mock_subject_count', 'mock_questions', 'mock_minutes']);
        $this->assertNull($exam->fresh()->mock_format);
    }

    public function test_the_built_in_exams_keep_their_format_and_a_custom_one_overrides_it(): void
    {
        [$jamb] = $this->makeExamAndSubject('JAMB', 'English Language');
        $mocks = app(MockService::class);

        $this->assertSame('JAMB UTME', $mocks->format($jamb)['label']);
        $this->assertSame(400, $mocks->format($jamb)['score_max']);

        $this->save($jamb, ['mock_custom' => 1, 'mock_label' => 'JAMB short test', 'mock_subject_count' => 1, 'mock_questions' => 10, 'mock_minutes' => 15], [$jamb->subjects()->first()->id => ['price' => 0]]);

        $this->assertSame('JAMB short test', $mocks->format($jamb->fresh())['label']);
    }

    public function test_the_app_catalog_carries_the_mock_format_of_every_active_exam(): void
    {
        [$exam] = $this->ielts();
        $this->save($exam, ['mock_custom' => 1, 'mock_label' => 'IELTS Reading test', 'mock_subject_count' => 1, 'mock_questions' => 30, 'mock_minutes' => 60]);
        $this->makeExamAndSubject('WAEC', 'Physics');
        $token = $this->makeUser(4, ['is_active' => 1])->createToken('t')->plainTextToken;

        $mock = $this->withToken($token)->getJson('/api/v1/catalog')->assertOk()->json('mock');

        $this->assertSame('IELTS Reading test', $mock['ielts']['label']);
        $this->assertSame(['default' => 30], $mock['ielts']['questions']);
        $this->assertSame(60, $mock['ielts']['minutes']);
        $this->assertSame('WAEC objective', $mock['waec']['label']);
    }

    public function test_a_hidden_exam_is_left_out_of_the_catalog_mocks(): void
    {
        [$exam] = $this->ielts();
        $this->save($exam, ['is_active' => 0]);
        $token = $this->makeUser(4, ['is_active' => 1])->createToken('t')->plainTextToken;

        $this->assertArrayNotHasKey('ielts', $this->withToken($token)->getJson('/api/v1/catalog')->json('mock'));
    }

    public function test_an_exam_with_no_papers_and_no_orders_can_be_deleted(): void
    {
        $this->actingAs($this->admin)->post('/console/exams', ['name' => 'GRE']);
        $gre = Exam::where('slug', 'gre')->firstOrFail();

        $this->actingAs($this->admin)->get(route('console.exams.edit', $gre))->assertSee('Delete this exam');
        $this->actingAs($this->admin)->delete(route('console.exams.destroy', $gre))->assertRedirect(route('console.exams.index'));

        $this->assertNull(Exam::find($gre->id));
    }

    public function test_an_exam_with_papers_or_orders_cannot_be_deleted(): void
    {
        [$exam] = $this->ielts();

        $this->actingAs($this->admin)->delete(route('console.exams.destroy', $exam))->assertSessionHas('error');
        $this->assertNotNull(Exam::find($exam->id));

        $exam->papers()->delete();
        $student = $this->makeUser(4, ['is_active' => 1]);
        Order::create(['user_id' => $student->id, 'reference' => 'TCB-TEST000001', 'method' => 'bank_transfer', 'exam_id' => $exam->id, 'amount' => 1000, 'access_days' => 365]);

        $this->actingAs($this->admin)->delete(route('console.exams.destroy', $exam))->assertSessionHas('error');
        $this->assertNotNull(Exam::find($exam->id));
    }

    public function test_only_admins_can_delete_or_change_an_exam(): void
    {
        [$exam] = $this->ielts();

        $this->actingAs($this->makeUser(3, ['is_active' => 1]))->delete(route('console.exams.destroy', $exam))->assertForbidden();
        $this->actingAs($this->makeUser(4, ['is_active' => 1]))->put(route('console.exams.update', $exam), ['name' => 'Hacked'])->assertForbidden();
        $this->assertSame('IELTS', $exam->fresh()->name);
    }
}
