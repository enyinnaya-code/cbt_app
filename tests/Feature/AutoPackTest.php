<?php

namespace Tests\Feature;

use App\Models\ContentPack;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Topic;
use App\Services\PackRefresher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesExamContent;
use Tests\TestCase;

/** Packs follow the published papers on their own, and a question's image clean-up can never delete other files. */
class AutoPackTest extends TestCase
{
    use RefreshDatabase, CreatesExamContent;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['testacbt.auto_packs' => true]);
        $this->app->forgetInstance(PackRefresher::class);
    }

    private function versions($exam, $subject): int
    {
        return (int) ContentPack::tier('full')->where('exam_id', $exam->id)->where('subject_id', $subject->id)->max('version');
    }

    private function finishRequest(): void
    {
        app(PackRefresher::class)->flush();   // what happens when a real request or command ends
    }

    public function test_publishing_a_paper_builds_its_pack_without_anyone_asking(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject, 2020, Paper::DRAFT);
        $this->makeQuestion($paper);
        $this->finishRequest();
        $this->assertSame(0, $this->versions($exam, $subject), 'a draft has no pack');

        $paper->update(['status' => Paper::PUBLISHED, 'published_at' => now()]);
        $this->finishRequest();

        $this->assertSame(1, $this->versions($exam, $subject));
        $this->assertTrue(ContentPack::current()->tier('full')->where('exam_id', $exam->id)->exists());
    }

    public function test_adding_editing_and_deleting_a_question_in_a_live_paper_refreshes_the_pack(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $a = $this->makeQuestion($paper, '<p>First</p>');
        $this->finishRequest();
        $this->assertSame(1, $this->versions($exam, $subject));

        $this->makeQuestion($paper, '<p>Second</p>');
        $this->finishRequest();
        $this->assertSame(2, $this->versions($exam, $subject));
        $this->assertSame(2, ContentPack::current()->tier('full')->first()->question_count);

        $a->update(['question' => '<p>First, corrected</p>']);
        $this->finishRequest();
        $this->assertSame(3, $this->versions($exam, $subject));

        $a->delete();
        $this->finishRequest();
        $this->assertSame(4, $this->versions($exam, $subject));
        $this->assertSame(1, ContentPack::current()->tier('full')->first()->question_count);
    }

    public function test_many_changes_in_one_request_cause_one_rebuild(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);

        foreach (range(1, 25) as $i) {
            $this->makeQuestion($paper, "<p>Q$i</p>");
        }
        $this->finishRequest();

        $this->assertSame(1, $this->versions($exam, $subject));
        $this->assertSame(25, ContentPack::current()->tier('full')->first()->question_count);
    }

    public function test_changes_to_a_draft_paper_do_not_touch_the_packs(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $live = $this->makePaper($exam, $subject, 2019);
        $this->makeQuestion($live);
        $this->finishRequest();

        $draft = $this->makePaper($exam, $subject, 2020, Paper::DRAFT);
        $this->makeQuestion($draft);
        $this->finishRequest();

        $this->assertSame(1, $this->versions($exam, $subject));
    }

    public function test_unpublishing_the_last_paper_removes_the_pack(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);
        $this->makeQuestion($paper);
        $this->finishRequest();
        $this->assertTrue(ContentPack::current()->exists());

        $paper->update(['status' => Paper::DRAFT, 'published_at' => null]);
        $this->finishRequest();

        $this->assertFalse(ContentPack::current()->exists());
    }

    public function test_renaming_a_topic_refreshes_the_pack_that_shows_it(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $topic = Topic::create(['subject_id' => $subject->id, 'name' => 'Algbra', 'slug' => 'algbra']);
        $paper = $this->makePaper($exam, $subject);
        $this->makeQuestion($paper, '<p>Q</p>', 'B', ['topic_id' => $topic->id]);
        $this->finishRequest();
        $before = $this->versions($exam, $subject);

        $topic->update(['name' => 'Algebra']);
        $this->finishRequest();

        $this->assertSame($before + 1, $this->versions($exam, $subject));
    }

    public function test_the_publish_command_builds_packs_by_default(): void
    {
        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject, 2020, Paper::DRAFT);
        $this->makeQuestion($paper);

        $this->artisan('testacbt:publish-papers', ['--all' => true])->assertSuccessful();

        $this->assertSame(1, $this->versions($exam, $subject));
    }

    public function test_deleting_a_question_never_deletes_files_outside_the_uploads_folder(): void
    {
        $dir = sys_get_temp_dir() . '/tc-img-' . uniqid();
        mkdir($dir . '/uploads', 0777, true);
        file_put_contents($dir . '/important.txt', 'keep me');
        file_put_contents($dir . '/index.php', '<?php // front door');
        file_put_contents($dir . '/uploads/own.png', 'png');
        $this->app->usePublicPath($dir);

        [$exam, $subject] = $this->makeExamAndSubject();
        $paper = $this->makePaper($exam, $subject);

        foreach (['/../important.txt', '/index.php', '/uploads/../index.php', '/uploads/../../important.txt'] as $evil) {
            $this->makeQuestion($paper, '<p>Q</p><img src="' . $evil . '">')->delete();
        }
        $mine = $this->makeQuestion($paper, '<p>Q</p><img src="/uploads/own.png">');
        $mine->delete();

        $this->assertFileExists($dir . '/important.txt');
        $this->assertFileExists($dir . '/index.php');
        $this->assertFileDoesNotExist($dir . '/uploads/own.png', 'its own uploaded picture is still cleaned up');

        foreach (glob($dir . '/uploads/*') ?: [] as $f) { @unlink($f); }
        @unlink($dir . '/important.txt');
        @unlink($dir . '/index.php');
        @rmdir($dir . '/uploads');
        @rmdir($dir);
    }
}
