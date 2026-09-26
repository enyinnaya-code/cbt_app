<?php

namespace Tests\Feature\Api;

use App\Models\ContentPack;
use App\Models\Paper;
use App\Services\PackBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

class PackTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function decode(ContentPack $pack): array
    {
        return json_decode(gzdecode(Storage::disk('local')->get($pack->path)), true);
    }

    public function test_builds_a_pack_from_published_papers_only(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject('JAMB', 'English Language', 'Use of English');
        $live = $this->makePaper($exam, $subject, 2020);
        $draft = $this->makePaper($exam, $subject, 2021, Paper::DRAFT);
        $this->makeQuestion($live, '<p>Live</p>');
        $this->makeQuestion($draft, '<p>Draft</p>');

        $result = app(PackBuilder::class)->build($exam, $subject);

        $this->assertSame('built', $result['status']);
        $data = $this->decode($result['pack']);
        $this->assertSame(1, $data['format']);
        $this->assertSame('Use of English', $data['subject']['display_name']);
        $this->assertCount(1, $data['papers']);
        $this->assertSame(2020, $data['papers'][0]['year']);
        $this->assertStringNotContainsString('Draft', json_encode($data));
        $this->assertSame([2020], $result['pack']->years);
    }

    public function test_keeps_passages_in_order_and_skips_questions_with_no_usable_answer(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $this->makeQuestion($paper, '<p>Read the passage</p>', '', ['not_question' => 1, 'options' => ['A' => null], 'answer' => null]);
        $good = $this->makeQuestion($paper, '<p>Good</p>', 'C');
        $this->makeQuestion($paper, '<p>No key</p>', '');
        $this->makeQuestion($paper, '<p>Key not in options</p>', 'E');

        $result = app(PackBuilder::class)->build($exam, $subject);
        $items = $this->decode($result['pack'])['papers'][0]['items'];

        $this->assertSame(['instruction', 'mcq'], array_column($items, 'type'));
        $this->assertSame($good->id, $items[1]['id']);
        $this->assertSame('C', $items[1]['answer']);
        $this->assertSame(1, $result['pack']->question_count, 'passages are not counted as questions');
        $this->assertStringContainsString('2 question(s) skipped', implode(' ', $result['warnings']));
    }

    public function test_rebuild_with_no_changes_keeps_the_same_version(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $this->makeQuestion($paper);

        $first = app(PackBuilder::class)->build($exam, $subject);
        $again = app(PackBuilder::class)->build($exam, $subject);

        $this->assertSame('unchanged', $again['status']);
        $this->assertSame(1, ContentPack::count());
        $this->assertSame($first['pack']->id, $again['pack']->id);
    }

    public function test_a_change_makes_a_new_version_and_old_files_are_pruned(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $q = $this->makeQuestion($paper);
        $builder = app(PackBuilder::class);

        $v1 = $builder->build($exam, $subject)['pack'];
        $q->update(['question' => '<p>Edited</p>']);
        $v2 = $builder->build($exam, $subject)['pack'];
        $q->update(['question' => '<p>Edited again</p>']);
        $v3 = $builder->build($exam, $subject)['pack'];

        $this->assertSame([1, 2, 3], [$v1->version, $v2->version, $v3->version]);
        $this->assertTrue($v3->fresh()->is_current);
        $this->assertFalse($v2->fresh()->is_current);
        Storage::disk('local')->assertMissing($v1->path);
        Storage::disk('local')->assertExists($v2->path);
        Storage::disk('local')->assertExists($v3->path);
        $this->assertDatabaseMissing('content_packs', ['id' => $v1->id]);
    }

    public function test_unpublishing_everything_retires_the_pack(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $this->makeQuestion($paper);
        app(PackBuilder::class)->build($exam, $subject);

        $paper->update(['status' => Paper::DRAFT]);
        $result = app(PackBuilder::class)->build($exam, $subject);

        $this->assertSame('retired', $result['status']);
        $this->assertSame(0, ContentPack::current()->count());
    }

    public function test_strips_scripts_and_event_handlers(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $this->makeQuestion($paper, '<p onclick="steal()">Hi</p><script>alert(1)</script><a href="javascript:evil()">x</a><iframe src="//e"></iframe>');

        $html = $this->decode(app(PackBuilder::class)->build($exam, $subject)['pack'])['papers'][0]['items'][0]['html'];

        $this->assertStringContainsString('Hi', $html);
        foreach (['onclick', '<script', 'alert(1)', 'javascript:', '<iframe'] as $bad) {
            $this->assertStringNotContainsString($bad, $html);
        }
    }

    public function test_uploaded_images_are_embedded_so_the_pack_works_offline(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);

        File::ensureDirectoryExists(public_path('uploads'));
        $name = 'pack-test-' . uniqid() . '.png';
        // 1x1 transparent PNG
        File::put(public_path("uploads/$name"), base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));

        try {
            $this->makeQuestion($paper, "<p><img src=\"http://old-host.test/uploads/$name\" alt=\"d\"></p><img src=\"http://old-host.test/uploads/missing.png\">");
            $builder = app(PackBuilder::class);
            $result = $builder->build($exam, $subject);
            $html = $this->decode($result['pack'])['papers'][0]['items'][0]['html'];

            $this->assertStringContainsString('src="data:image/png;base64,', $html);
            $this->assertStringNotContainsString($name, $html);
            $this->assertStringContainsString('missing.png', $html, 'unavailable images are left as links');
            $this->assertStringContainsString('1 image(s) not found', implode(' ', $result['warnings']));
        } finally {
            File::delete(public_path("uploads/$name"));
        }
    }

    public function test_image_paths_cannot_escape_the_uploads_folder(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $this->makeQuestion($paper, '<img src="http://x.test/uploads/../../.env"><img src="http://x.test/uploads/%2e%2e%2f.env">');

        $html = $this->decode(app(PackBuilder::class)->build($exam, $subject)['pack'])['papers'][0]['items'][0]['html'];

        $this->assertStringNotContainsString('data:', $html);
    }

    // ---- HTTP ----

    private function bearer(): array
    {
        return ['Authorization' => 'Bearer ' . $this->makeUser()->createToken('t')->plainTextToken];
    }

    public function test_catalog_lists_exams_subjects_and_pack_sizes(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject('JAMB', 'English Language', 'Use of English');
        [, $chem] = $this->makeExamAndSubject('JAMB', 'Chemistry');
        $paper = $this->makePaper($exam, $subject, 2019);
        $this->makeQuestion($paper);
        $pack = app(PackBuilder::class)->build($exam, $subject)['pack'];

        $r = $this->getJson('/api/v1/catalog', $this->bearer())->assertOk();

        $subjects = collect($r->json('exams.0.subjects'))->keyBy('slug');
        $this->assertSame('Use of English', $subjects['english-language']['display_name']);
        $this->assertSame($pack->size_bytes, $subjects['english-language']['pack']['size_bytes']);
        $this->assertSame($pack->sha256, $subjects['english-language']['pack']['sha256']);
        $this->assertNull($subjects['chemistry']['pack'], 'subjects with no published papers have no pack');
    }

    public function test_catalog_supports_etag_so_unchanged_checks_cost_almost_nothing(): void
    {
        $this->makeExamAndSubject();
        $headers = $this->bearer();

        $etag = $this->getJson('/api/v1/catalog', $headers)->assertOk()->headers->get('ETag');
        $this->getJson('/api/v1/catalog', $headers + ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_pack_download_matches_the_advertised_checksum(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $this->makeQuestion($this->makePaper($exam, $subject));
        $pack = app(PackBuilder::class)->build($exam, $subject)['pack'];

        $r = $this->get("/api/v1/packs/{$exam->slug}/{$subject->slug}", $this->bearer())->assertOk();
        $file = file_get_contents($r->baseResponse->getFile()->getPathname());

        $this->assertSame($pack->sha256, hash('sha256', $file));
        $this->assertSame($pack->sha256, $r->headers->get('X-Pack-Sha256'));
        $this->assertSame(1, json_decode(gzdecode($file), true)['format']);
    }

    public function test_pack_download_supports_resuming_with_range_requests(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $this->makeQuestion($this->makePaper($exam, $subject));
        $pack = app(PackBuilder::class)->build($exam, $subject)['pack'];

        $r = $this->get("/api/v1/packs/{$exam->slug}/{$subject->slug}", $this->bearer() + ['Range' => 'bytes=10-']);

        $r->assertStatus(206);
        $last = $pack->size_bytes - 1;
        $this->assertSame("bytes 10-{$last}/{$pack->size_bytes}", $r->headers->get('Content-Range'));

        // The resumed bytes are exactly the tail of the file, so appending them completes the download.
        ob_start();
        $r->baseResponse->sendContent();
        $tail = ob_get_clean();
        $full = file_get_contents(Storage::disk('local')->path($pack->path));
        $this->assertSame(substr($full, 10), $tail);
    }

    public function test_pack_download_needs_sign_in_and_a_real_pack(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();

        $this->getJson("/api/v1/packs/{$exam->slug}/{$subject->slug}")->assertStatus(401);
        $this->getJson("/api/v1/packs/{$exam->slug}/{$subject->slug}", $this->bearer())->assertStatus(404);
    }

    // ---- Commands ----

    public function test_publish_and_build_commands_work_end_to_end(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject, 2019, Paper::DRAFT);
        $this->makeQuestion($paper);

        $this->artisan('testacbt:publish-papers', ['--all' => true, '--build' => true])->assertSuccessful();

        $this->assertSame('published', $paper->fresh()->status);
        $this->assertNotNull($paper->fresh()->published_at);
        $this->assertSame(1, ContentPack::current()->count());

        $this->artisan('testacbt:publish-papers', ['--all' => true, '--unpublish' => true, '--build' => true])->assertSuccessful();
        $this->assertSame('draft', $paper->fresh()->status);
        $this->assertSame(0, ContentPack::current()->count());
    }

    public function test_publish_command_needs_ids_or_all(): void
    {
        $this->artisan('testacbt:publish-papers')->assertFailed();
    }
}
