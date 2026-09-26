<?php

namespace Tests\Feature;

use App\Models\Paper;
use App\Models\Question;
use Database\Seeders\ExamCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TagLegacyTestsCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $testId;
    private string $csv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ExamCatalogSeeder::class);

        $userId = DB::table('users')->insertGetId([
            'name' => 'T', 'email' => 't@example.com', 'password' => 'x', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('sections')->insert(['id' => 1, 'section_name' => 'S', 'created_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('courses')->insert(['id' => 1, 'course_name' => 'C', 'section_id' => 1, 'added_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('school_classes')->insert(['id' => 1, 'name' => 'K', 'section_id' => 1, 'added_by' => $userId, 'created_at' => now(), 'updated_at' => now()]);
        $this->testId = DB::table('tests')->insertGetId([
            'test_name' => 'Old mock', 'created_by' => $userId, 'section_id' => 1, 'course_id' => 1, 'class_id' => 1,
            'test_type' => 'multiple_choice', 'duration' => 30, 'pass_mark' => 10, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ([1, 2, 3] as $n) {
            Question::create(['test_id' => $this->testId, 'question' => "Q$n", 'answer' => 'A', 'mark' => 1, 'not_question' => 0]);
        }

        $this->csv = tempnam(sys_get_temp_dir(), 'map');
    }

    protected function tearDown(): void
    {
        @unlink($this->csv);
        parent::tearDown();
    }

    public function test_tags_questions_into_a_paper_and_is_idempotent(): void
    {
        // A UTF-8 BOM and quoted header, as Excel and PowerShell write.
        file_put_contents($this->csv, "\xEF\xBB\xBF\"legacy_test_id\",\"exam\",\"subject\",\"year\"\n{$this->testId},jamb,use of english,2020\n");

        $this->artisan('testacbt:tag-legacy', ['file' => $this->csv])->assertSuccessful();
        $this->artisan('testacbt:tag-legacy', ['file' => $this->csv])->assertSuccessful();

        $paper = Paper::firstOrFail();
        $this->assertSame(1, Paper::count());
        $this->assertSame('draft', $paper->status);
        $this->assertSame(2020, $paper->year);
        $this->assertSame(30, $paper->duration_minutes);
        $this->assertSame(3, Question::where('paper_id', $paper->id)->count());
        $this->assertSame(3, Question::where('test_id', $this->testId)->count(), 'legacy link is kept');
    }

    public function test_a_bad_row_saves_nothing(): void
    {
        file_put_contents($this->csv, "legacy_test_id,exam,subject,year\n{$this->testId},NABTEB,Physics,2020\n");

        $this->artisan('testacbt:tag-legacy', ['file' => $this->csv])->assertFailed();

        $this->assertSame(0, Paper::count());
        $this->assertSame(0, Question::whereNotNull('paper_id')->count());
    }

    public function test_dry_run_saves_nothing(): void
    {
        file_put_contents($this->csv, "legacy_test_id,exam,subject,year\n{$this->testId},WAEC,Physics,2019\n");

        $this->artisan('testacbt:tag-legacy', ['file' => $this->csv, '--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, Paper::count());
    }
}
