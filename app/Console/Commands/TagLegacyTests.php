<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\Subject;
use App\Models\Test;
use App\Services\LegacyTagger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TagLegacyTests extends Command
{
    protected $signature = 'testacbt:tag-legacy
        {file? : CSV with columns legacy_test_id,exam,subject,year}
        {--template= : Write a blank mapping CSV of every untagged legacy test to this path, then exit}
        {--publish : Create the papers as published instead of draft}
        {--dry-run : Validate and report without saving anything}';

    protected $description = 'Attach questions from pre-TestaCBT school tests to exam/subject/year papers';

    public function handle(LegacyTagger $tagger): int
    {
        if ($path = $this->option('template')) {
            return $this->writeTemplate($path, $tagger);
        }

        $file = $this->argument('file');
        if (! $file || ! is_readable($file)) {
            $this->error('Give a readable CSV path, or use --template=<path> to generate one.');
            return self::FAILURE;
        }

        $rows = $this->readCsv($file);
        if ($rows === null) {
            $this->error('CSV must have the header: legacy_test_id,exam,subject,year');
            return self::FAILURE;
        }

        $created = $moved = $skipped = 0;
        $problems = [];

        DB::beginTransaction();
        try {
            foreach ($rows as $line => $row) {
                [$id, $examName, $subjectName, $year] = [$row['legacy_test_id'], $row['exam'], $row['subject'], $row['year']];

                if ($examName === '' && $subjectName === '' && $year === '') {
                    $skipped++;   // blank row in the template: not tagged yet
                    continue;
                }

                $test = Test::find($id);
                $exam = Exam::where('slug', Str::slug($examName))->first();
                $subject = $exam ? $this->findSubject($exam, $subjectName) : null;

                if (! $test) { $problems[] = "line $line: no legacy test with id '$id'"; continue; }
                if (! $exam) { $problems[] = "line $line: unknown exam '$examName'"; continue; }
                if (! $subject) { $problems[] = "line $line: unknown subject '$subjectName'"; continue; }
                if (! LegacyTagger::yearIsValid($year)) {
                    $problems[] = "line $line: bad year '$year'"; continue;
                }

                $result = $tagger->tag($test, $exam, $subject, (int) $year, (bool) $this->option('publish'));
                $result['created'] && $created++;
                $moved += $result['moved'];
            }

            if ($problems) {
                DB::rollBack();
                $this->error('Nothing was saved. Fix these rows and run again:');
                foreach ($problems as $p) { $this->line("  - $p"); }
                return self::FAILURE;
            }

            $this->option('dry-run') ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $verb = $this->option('dry-run') ? 'Would create' : 'Created';
        $this->info("$verb $created paper(s), tagged $moved question(s), skipped $skipped blank row(s).");

        return self::SUCCESS;
    }

    /** Matches the subject's own name, or the name that exam uses for it (e.g. JAMB's "Use of English"). */
    private function findSubject(Exam $exam, string $name): ?Subject
    {
        $slug = Str::slug($name);

        return Subject::where('slug', $slug)->first()
            ?? $exam->subjects->first(fn (Subject $s) => $s->pivot->display_name && Str::slug($s->pivot->display_name) === $slug);
    }

    private function writeTemplate(string $path, LegacyTagger $tagger): int
    {
        $tests = $tagger->untagged();

        $out = fopen($path, 'w');
        fputcsv($out, ['legacy_test_id', 'exam', 'subject', 'year', 'test_name', 'questions']);
        foreach ($tests as $t) {
            fputcsv($out, [$t->id, '', '', '', $t->test_name, $t->question_count]);
        }
        fclose($out);

        $this->info("Wrote {$tests->count()} legacy test(s) to $path. Fill in exam, subject and year, then run this command with the file.");
        return self::SUCCESS;
    }

    /** @return array<int, array{legacy_test_id:string,exam:string,subject:string,year:string}>|null */
    private function readCsv(string $file): ?array
    {
        $h = fopen($file, 'r');
        // Skip a UTF-8 BOM (Excel and PowerShell add one) before parsing, or a quoted first header won't parse.
        if (fread($h, 3) !== "\xEF\xBB\xBF") {
            rewind($h);
        }
        $header = array_map(fn ($c) => strtolower(trim((string) $c)), fgetcsv($h) ?: []);
        foreach (['legacy_test_id', 'exam', 'subject', 'year'] as $need) {
            if (! in_array($need, $header, true)) { fclose($h); return null; }
        }

        $rows = [];
        $line = 1;
        while (($cells = fgetcsv($h)) !== false) {
            $line++;
            if ($cells === [null]) { continue; }
            $row = array_combine($header, array_pad($cells, count($header), ''));
            $rows[$line] = array_map('trim', array_intersect_key($row, array_flip(['legacy_test_id', 'exam', 'subject', 'year'])));
        }
        fclose($h);

        return $rows;
    }
}
