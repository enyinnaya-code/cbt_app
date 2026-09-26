<?php

namespace Tests\Feature\Api;

use App\Models\ContentPack;
use App\Models\Entitlement;
use App\Models\Exam;
use App\Models\Subject;
use App\Models\User;
use App\Services\PackBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** The app gets a small free pack until a subject is unlocked, then the full one. */
class PackAccessTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    private Exam $exam;
    private Subject $subject;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        // Priced at 1,500 with 3 free questions.
        [$this->exam, $this->subject] = $this->makeExamAndSubject('JAMB', 'Physics', null, 1500);
        $this->exam->subjects()->updateExistingPivot($this->subject->id, ['free_questions' => 3]);

        $old = $this->makePaper($this->exam, $this->subject, 2019);
        $new = $this->makePaper($this->exam, $this->subject, 2021);
        foreach (range(1, 4) as $i) { $this->makeQuestion($old, "<p>Old $i</p>"); }
        $this->makeQuestion($new, '<p>Read this passage</p>', '', ['not_question' => 1]);
        foreach (range(1, 4) as $i) { $this->makeQuestion($new, "<p>New $i</p>"); }

        $this->student = $this->makeUser(4, ["is_active" => 1]);
        app(PackBuilder::class)->build($this->exam, $this->subject);
    }

    private function decode(ContentPack $pack): array
    {
        return json_decode(gzdecode(Storage::disk('local')->get($pack->path)), true);
    }

    private function pack(string $tier): ?ContentPack
    {
        return ContentPack::current()->tier($tier)->where('exam_id', $this->exam->id)->first();
    }

    private function catalogSubject(?User $user = null): array
    {
        $r = $this->actingAs($user ?? $this->student, 'sanctum')->getJson('/api/v1/catalog')->assertOk();

        return $r->json('exams.0.subjects.0');
    }

    private function unlock(): void
    {
        Entitlement::create(['user_id' => $this->student->id, 'exam_id' => $this->exam->id, 'subject_id' => $this->subject->id, 'expires_at' => now()->addDays(30)]);
    }

    public function test_a_paid_subject_gets_a_full_pack_and_a_free_sample_pack(): void
    {
        $this->assertSame(8, $this->pack('full')->question_count);
        $this->assertSame(3, $this->pack('free')->question_count);
        $this->assertLessThan($this->pack('full')->size_bytes, $this->pack('free')->size_bytes);
        $this->assertSame(1, $this->pack('free')->version);
    }

    public function test_the_free_pack_holds_only_the_free_questions_with_their_passage(): void
    {
        $data = $this->decode($this->pack('free'));

        $this->assertSame('free', $data['tier']);
        $this->assertCount(1, $data['papers'], 'the 2019 paper has no free questions, so it is left out');
        $items = $data['papers'][0]['items'];
        $this->assertSame(['instruction', 'mcq', 'mcq', 'mcq'], array_column($items, 'type'));
        $this->assertStringContainsString('New 1', $items[1]['html']);
        $this->assertStringNotContainsString('New 4', json_encode($data));
        $this->assertStringNotContainsString('Old', json_encode($data));
    }

    public function test_a_passage_is_left_out_when_none_of_its_questions_are_free(): void
    {
        $this->exam->subjects()->updateExistingPivot($this->subject->id, ['free_questions' => 0]);
        $late = $this->makePaper($this->exam, $this->subject, 2023);
        $this->makeQuestion($late, '<p>Late passage</p>', '', ['not_question' => 1]);
        $this->makeQuestion($late, '<p>Late question</p>');
        $this->exam->subjects()->updateExistingPivot($this->subject->id, ['free_questions' => 1]);

        app(PackBuilder::class)->build($this->exam, $this->subject);
        $data = $this->decode($this->pack('free'));

        $this->assertSame(['instruction', 'mcq'], array_column($data['papers'][0]['items'], 'type'));
        $this->assertStringContainsString('Late passage', $data['papers'][0]['items'][0]['html']);
    }

    public function test_the_full_pack_is_unchanged_by_the_free_sample(): void
    {
        $data = $this->decode($this->pack('full'));

        $this->assertSame('full', $data['tier']);
        $this->assertCount(2, $data['papers']);
        $this->assertSame(8, collect($data['papers'])->sum(fn ($p) => collect($p['items'])->where('type', 'mcq')->count()));
    }

    public function test_no_free_pack_is_made_for_a_free_subject_or_when_there_are_no_free_questions(): void
    {
        $this->exam->subjects()->updateExistingPivot($this->subject->id, ['price' => 0]);
        app(PackBuilder::class)->build($this->exam, $this->subject);
        $this->assertNull($this->pack('free'), 'a free subject needs no separate sample');

        $this->exam->subjects()->updateExistingPivot($this->subject->id, ['price' => 1500, 'free_questions' => 0]);
        app(PackBuilder::class)->build($this->exam, $this->subject);
        $this->assertNull($this->pack('free'));
    }

    public function test_a_locked_student_is_told_it_is_the_free_sample_and_what_unlocking_gives(): void
    {
        $s = $this->catalogSubject();

        $this->assertSame('free', $s['access']);
        $this->assertSame('free', $s['pack']['tier']);
        $this->assertSame(3, $s['pack']['question_count']);
        $this->assertSame(1500, $s['price']);
        $this->assertSame(3, $s['free_questions']);
        $this->assertSame(8, $s['full_question_count']);
        $this->assertNull($s['expires_at']);
    }

    public function test_an_unlocked_student_is_offered_the_full_pack(): void
    {
        $this->unlock();

        $s = $this->catalogSubject();

        $this->assertSame('full', $s['access']);
        $this->assertSame('full', $s['pack']['tier']);
        $this->assertSame(8, $s['pack']['question_count']);
        $this->assertNotNull($s['expires_at']);
    }

    public function test_the_catalog_carries_the_links_for_unlocking(): void
    {
        $r = $this->actingAs($this->student, 'sanctum')->getJson('/api/v1/catalog')->assertOk();

        $this->assertStringEndsWith('/pricing', $r->json('urls.pricing'));
        $this->assertStringEndsWith('/checkout', $r->json('urls.checkout'));
    }

    public function test_unlocking_changes_the_catalog_so_a_cached_copy_is_not_reused(): void
    {
        $token = $this->student->createToken('t')->plainTextToken;
        $etag = $this->withToken($token)->getJson('/api/v1/catalog')->headers->get('ETag');
        $this->withToken($token)->withHeader('If-None-Match', $etag)->getJson('/api/v1/catalog')->assertStatus(304);

        $this->unlock();

        $this->withToken($token)->withHeader('If-None-Match', $etag)->getJson('/api/v1/catalog')->assertOk();
    }

    public function test_one_students_purchase_does_not_change_another_students_catalog(): void
    {
        $this->unlock();

        $this->assertSame('free', $this->catalogSubject($this->makeUser(4, ["is_active" => 1]))['access']);
    }

    public function test_the_download_serves_the_free_pack_until_unlocked(): void
    {
        $token = $this->student->createToken('t')->plainTextToken;

        $r = $this->withToken($token)->get('/api/v1/packs/jamb/physics')->assertOk();
        $this->assertSame('free', $r->headers->get('X-Pack-Tier'));
        $this->assertSame($this->pack('free')->sha256, $r->headers->get('X-Pack-Sha256'));
        $this->assertSame(3, json_decode(gzdecode(file_get_contents($r->baseResponse->getFile()->getPathname())), true)['papers'][0]['items'][3]['id'] ? 3 : 0);

        $this->unlock();

        $r = $this->withToken($token)->get('/api/v1/packs/jamb/physics')->assertOk();
        $this->assertSame('full', $r->headers->get('X-Pack-Tier'));
        $this->assertSame($this->pack('full')->sha256, $r->headers->get('X-Pack-Sha256'));
    }

    public function test_an_expired_purchase_goes_back_to_the_free_pack(): void
    {
        $this->unlock();
        Entitlement::query()->update(['expires_at' => now()->subMinute()]);
        $token = $this->student->createToken('t')->plainTextToken;

        $this->withToken($token)->get('/api/v1/packs/jamb/physics')->assertOk()->assertHeader('X-Pack-Tier', 'free');
        $this->assertSame('free', $this->catalogSubject()['access']);
    }

    public function test_a_locked_student_with_no_free_pack_gets_nothing_to_download(): void
    {
        $this->exam->subjects()->updateExistingPivot($this->subject->id, ['free_questions' => 0]);
        app(PackBuilder::class)->build($this->exam, $this->subject);
        $token = $this->student->createToken('t')->plainTextToken;

        $this->withToken($token)->get('/api/v1/packs/jamb/physics')->assertNotFound();
        $this->assertNull($this->catalogSubject()['pack']);
    }

    public function test_examiners_and_admins_are_offered_the_full_pack(): void
    {
        $this->assertSame('full', $this->catalogSubject($this->makeUser(3, ["is_active" => 1]))['access']);
        $this->assertSame('full', $this->catalogSubject($this->makeUser(2, ["is_active" => 1]))['pack']['tier']);
    }

    public function test_changing_the_free_question_count_rebuilds_only_the_free_pack(): void
    {
        $admin = $this->makeUser(2, ["is_active" => 1]);
        $fullBefore = $this->pack('full')->version;

        $this->actingAs($admin)->put("/console/exams/{$this->exam->id}", [
            'name' => 'JAMB', 'subjects' => [$this->subject->id => ['price' => 1500, 'free_questions' => 5]],
        ])->assertRedirect();

        $this->assertSame(5, $this->pack('free')->question_count);
        $this->assertSame(2, $this->pack('free')->version);
        $this->assertSame($fullBefore, $this->pack('full')->version);

        // Saving again with the same numbers must not create yet another version.
        $this->actingAs($admin)->put("/console/exams/{$this->exam->id}", [
            'name' => 'JAMB', 'subjects' => [$this->subject->id => ['price' => 1500, 'free_questions' => 5]],
        ]);
        $this->assertSame(2, $this->pack('free')->version);
    }

    public function test_making_a_subject_free_removes_its_free_pack(): void
    {
        $this->actingAs($this->makeUser(2, ["is_active" => 1]))->put("/console/exams/{$this->exam->id}", [
            'name' => 'JAMB', 'is_active' => 1, 'subjects' => [$this->subject->id => ['price' => 0]],
        ])->assertRedirect();

        $this->assertNull($this->pack('free'));
        $this->assertSame('full', $this->catalogSubject()['access']);
    }

    public function test_the_default_price_and_free_count_settings_rebuild_the_free_packs(): void
    {
        $this->exam->subjects()->updateExistingPivot($this->subject->id, ['free_questions' => null]);
        $base = ['app_play_store_url' => '', 'app_app_store_url' => '', 'app_apk_url' => '', 'support_email' => '', 'support_whatsapp' => ''];

        $this->actingAs($this->makeUser(2, ["is_active" => 1]))->put('/console/settings', $base + ['pricing_free_questions' => '4'])->assertSessionHasNoErrors();

        $this->assertSame(4, $this->pack('free')->question_count);
    }

    public function test_the_console_pack_list_counts_each_subject_once(): void
    {
        $this->actingAs($this->makeUser(2, ["is_active" => 1]))->get('/console/packs')->assertOk()->assertSee('v1');
        $this->assertSame(2, ContentPack::current()->count());
        $this->assertSame(1, ContentPack::current()->tier('full')->count());
    }
}
