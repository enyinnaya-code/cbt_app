<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Setting;
use App\Models\Subject;
use App\Services\MockService;
use App\Services\Pricing;
use Database\Seeders\ExamCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class ExamManagementTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    public function test_only_admins_can_manage_exams(): void
    {
        [$exam] = $this->makeExamAndSubject('WAEC', 'Physics');

        $this->get('/console/exams')->assertRedirect('/login');
        $this->actingAs($this->makeUser(4))->get('/console/exams')->assertForbidden();
        $this->actingAs($this->makeUser(3))->get('/console/exams')->assertForbidden();
        $this->actingAs($this->makeUser(3))->put("/console/exams/{$exam->id}", ['name' => 'Hacked'])->assertForbidden();
        $this->actingAs($this->makeUser(2))->get('/console/exams')->assertOk()->assertSee('WAEC');
    }

    public function test_seeding_adds_post_utme_and_igcse_with_their_subjects(): void
    {
        $this->seed(ExamCatalogSeeder::class);

        $postUtme = Exam::where('slug', 'post-utme')->firstOrFail();
        $igcse = Exam::where('slug', 'igcse')->firstOrFail();

        $this->assertTrue($postUtme->subjects->contains('slug', 'general-studies'));
        $this->assertTrue($igcse->subjects->contains('slug', 'computer-science'));
        $this->assertSame('Use of English', $postUtme->subjects->firstWhere('slug', 'english-language')->pivot->display_name);
        $this->assertNull($igcse->subjects->firstWhere('slug', 'english-language')->pivot->display_name);
    }

    public function test_reseeding_keeps_what_an_admin_changed(): void
    {
        $this->seed(ExamCatalogSeeder::class);

        $jamb = Exam::where('slug', 'jamb')->firstOrFail();
        $jamb->update(['name' => 'JAMB UTME', 'bundle_price' => 4000]);
        $jamb->subjects()->updateExistingPivot(Subject::where('slug', 'physics')->value('id'), ['display_name' => 'Physics (Science)', 'price' => 1800, 'free_questions' => 50]);

        $this->seed(ExamCatalogSeeder::class);

        $jamb->refresh();
        $physics = $jamb->subjects->firstWhere('slug', 'physics')->pivot;
        $this->assertSame('JAMB UTME', $jamb->name);
        $this->assertSame(4000, $jamb->bundle_price);
        $this->assertSame('Physics (Science)', $physics->display_name);
        $this->assertSame(1800, $physics->price);
        $this->assertSame(50, $physics->free_questions);
        $this->assertSame(5, Exam::count());
    }

    public function test_post_utme_and_igcse_have_their_own_mock_formats(): void
    {
        $this->seed(ExamCatalogSeeder::class);
        $mocks = app(MockService::class);

        $this->assertSame('Post-UTME', $mocks->format(Exam::where('slug', 'post-utme')->first())['label']);
        $this->assertSame(40, $mocks->plannedQuestions($mocks->format(Exam::where('slug', 'igcse')->first()), 'physics'));
    }

    public function test_an_admin_adds_an_exam_and_a_subject(): void
    {
        $admin = $this->makeUser(2);

        $this->actingAs($admin)->post('/console/exams', ['name' => 'Common Entrance'])->assertRedirect();
        $exam = Exam::where('slug', 'common-entrance')->firstOrFail();
        $this->assertTrue($exam->is_active);

        $this->actingAs($admin)->post('/console/subjects', ['name' => 'Further Mathematics', 'code' => 'FM', 'exam_id' => $exam->id])->assertRedirect();
        $this->assertTrue($exam->subjects()->where('slug', 'further-mathematics')->exists());

        $this->actingAs($admin)->post('/console/exams', ['name' => 'Common Entrance'])->assertSessionHasErrors('name');
        $this->actingAs($admin)->post('/console/subjects', ['name' => 'Further Mathematics'])->assertSessionHasErrors('name');
    }

    public function test_an_admin_sets_prices_names_and_bundle_price(): void
    {
        $admin = $this->makeUser(2);
        [$exam, $subject] = $this->makeExamAndSubject('WAEC', 'Physics');

        $this->actingAs($admin)->put("/console/exams/{$exam->id}", [
            'name' => 'WAEC', 'is_active' => 1, 'sort_order' => 2, 'bundle_price' => 5000,
            'subjects' => [$subject->id => ['display_name' => 'Physics (SSCE)', 'price' => 1500, 'free_questions' => 20]],
        ])->assertRedirect();

        $exam->refresh();
        $pivot = $exam->subjects()->first()->pivot;
        $this->assertSame(5000, $exam->bundle_price);
        $this->assertSame(1500, $pivot->price);
        $this->assertSame(20, $pivot->free_questions);
        $this->assertSame('Physics (SSCE)', $pivot->display_name);
    }

    public function test_empty_price_boxes_mean_use_the_default_and_zero_means_free(): void
    {
        $admin = $this->makeUser(2);
        [$exam, $subject] = $this->makeExamAndSubject('WAEC', 'Physics');
        $exam->subjects()->updateExistingPivot($subject->id, ['price' => 900]);

        $this->actingAs($admin)->put("/console/exams/{$exam->id}", ['name' => 'WAEC', 'subjects' => [$subject->id => ['price' => '', 'free_questions' => '']]]);
        $this->assertSame(Pricing::DEFAULT_PRICE, Pricing::subjectPrice($exam, $subject));
        $this->assertSame(Pricing::DEFAULT_FREE_QUESTIONS, Pricing::freeQuestions(Pricing::pivot($exam, $subject)));

        $this->actingAs($admin)->put("/console/exams/{$exam->id}", ['name' => 'WAEC', 'subjects' => [$subject->id => ['price' => '0']]]);
        $this->assertTrue(Pricing::isFreeSubject($exam, $subject));
    }

    public function test_the_site_default_price_is_used_when_a_subject_has_none(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject('WAEC', 'Physics', null, null);

        Setting::put(['pricing.default_price' => '2500', 'pricing.free_questions' => '10', 'pricing.access_days' => '90']);

        $this->assertSame(2500, Pricing::subjectPrice($exam, $subject));
        $this->assertSame(10, Pricing::freeQuestions(Pricing::pivot($exam, $subject)));
        $this->assertSame(90, Pricing::accessDays());
        $this->assertSame('₦2,500', Pricing::naira(2500));
    }

    public function test_bad_prices_are_rejected_and_a_slug_never_changes(): void
    {
        $admin = $this->makeUser(2);
        [$exam, $subject] = $this->makeExamAndSubject('WAEC', 'Physics');

        $this->actingAs($admin)->put("/console/exams/{$exam->id}", ['name' => 'WAEC', 'subjects' => [$subject->id => ['price' => '-5']]])->assertSessionHasErrors('subjects.' . $subject->id . '.price');
        $this->actingAs($admin)->put("/console/exams/{$exam->id}", ['name' => 'WAEC', 'bundle_price' => 'abc'])->assertSessionHasErrors('bundle_price');

        $this->actingAs($admin)->put("/console/exams/{$exam->id}", ['name' => 'West African Exams'])->assertRedirect();
        $this->assertSame('waec', $exam->fresh()->slug);
    }

    public function test_prices_cannot_be_set_for_subjects_an_exam_does_not_have(): void
    {
        $admin = $this->makeUser(2);
        [$exam] = $this->makeExamAndSubject('WAEC', 'Physics');
        $other = Subject::create(['name' => 'History', 'slug' => 'history', 'code' => 'Hi']);

        $this->actingAs($admin)->put("/console/exams/{$exam->id}", ['name' => 'WAEC', 'subjects' => [$other->id => ['price' => 100]]])->assertRedirect();

        $this->assertFalse($exam->subjects()->where('subjects.id', $other->id)->exists());
    }

    public function test_a_subject_with_papers_cannot_be_removed_from_its_exam(): void
    {
        $admin = $this->makeUser(2);
        [$exam, $subject] = $this->makeExamAndSubject('WAEC', 'Physics');
        $this->makePaper($exam, $subject, 2019);
        [, $empty] = $this->makeExamAndSubject('WAEC', 'Biology');

        $this->actingAs($admin)->delete("/console/exams/{$exam->id}/subjects/{$subject->id}")->assertSessionHas('error');
        $this->assertTrue($exam->subjects()->where('subjects.id', $subject->id)->exists());

        $this->actingAs($admin)->delete("/console/exams/{$exam->id}/subjects/{$empty->id}")->assertSessionHas('success');
        $this->assertFalse($exam->subjects()->where('subjects.id', $empty->id)->exists());
    }

    public function test_a_hidden_exam_leaves_the_student_pages(): void
    {
        $admin = $this->makeUser(2);
        [$exam] = $this->makeExamAndSubject('WAEC', 'Physics');
        $this->makeExamAndSubject('NECO', 'Physics');

        $this->actingAs($admin)->put("/console/exams/{$exam->id}", ['name' => 'WAEC', 'is_active' => 0])->assertRedirect();

        $this->assertFalse($exam->fresh()->is_active);
        $this->actingAs($this->makeUser(4))->get('/practice')->assertOk()->assertSee('NECO')->assertDontSee('WAEC');
    }
}
