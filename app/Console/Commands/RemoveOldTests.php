<?php

namespace App\Console\Commands;

use App\Models\Paper;
use App\Services\PackRefresher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clears out the school-era tests that existed before TestaCBT: the old tests, their submissions, and the papers that were
 * made from them. Everything else (users, exams, subjects, papers written for TestaCBT, orders) is left alone. It shows
 * what it will remove and asks first.
 */
class RemoveOldTests extends Command
{
    protected $signature = 'testacbt:remove-old-tests {--force : Do not ask for confirmation}';

    protected $description = 'Delete the old school tests, their submissions and the papers made from them';

    public function handle(PackRefresher $packs): int
    {
        $papers = Paper::whereNotNull('legacy_test_id')->get();
        $testIds = DB::table('tests')->pluck('id');

        $this->table(['What', 'How many'], [
            ['Old tests', $testIds->count()],
            ['Papers made from them', $papers->count()],
            ['Questions in those papers', DB::table('questions')->whereIn('paper_id', $papers->pluck('id'))->count()],
            ['Old test submissions', DB::table('test_submissions')->whereIn('test_id', $testIds)->count()],
            ['Old student exam records', DB::table('students_exams')->count()],
        ]);

        if (! $this->option('force') && ! $this->confirm('Delete all of this? Take a database backup first. This cannot be undone.')) {
            $this->warn('Nothing was deleted.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($papers, $testIds) {
            foreach ($papers as $paper) {
                foreach ($paper->questions as $question) { $question->delete(); }   // also removes the pictures it uploaded
                $paper->delete();
            }
            DB::table('user_question_orders')->whereIn('test_id', $testIds)->delete();
            DB::table('test_submissions')->whereIn('test_id', $testIds)->delete();
            DB::table('questions')->whereIn('test_id', $testIds)->delete();
            DB::table('students_exams')->delete();
            DB::table('tests')->whereIn('id', $testIds)->delete();
        });

        $packs->flush();   // packs with no papers left are removed

        $this->info('Done. The old tests and their papers are gone.');

        return self::SUCCESS;
    }
}
