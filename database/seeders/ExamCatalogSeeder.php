<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the exam bodies and the subjects each one offers. Safe to re-run: it adds what is missing and
 * never changes prices or names an admin has edited.
 */
class ExamCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $exams = ['WAEC', 'NECO', 'JAMB', 'Post-UTME', 'IGCSE'];

        // subject => [code, [exam => display name or null]]
        $subjects = [
            'English Language'      => ['En', ['WAEC' => null, 'NECO' => null, 'JAMB' => 'Use of English', 'Post-UTME' => 'Use of English', 'IGCSE' => null]],
            'Mathematics'           => ['Ma', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null, 'IGCSE' => null]],
            'Physics'               => ['Ph', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null, 'IGCSE' => null]],
            'Chemistry'             => ['Ch', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null, 'IGCSE' => null]],
            'Biology'               => ['Bi', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null, 'IGCSE' => null]],
            'Economics'             => ['Ec', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null, 'IGCSE' => null]],
            'Government'            => ['Go', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null]],
            'Literature in English' => ['Li', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null]],
            'Commerce'              => ['Co', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null]],
            'Financial Accounting'  => ['Ac', ['WAEC' => null, 'NECO' => null, 'JAMB' => 'Principles of Accounts', 'Post-UTME' => 'Principles of Accounts', 'IGCSE' => 'Accounting']],
            'Geography'             => ['Ge', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null, 'IGCSE' => null]],
            'Agricultural Science'  => ['Ag', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null]],
            'Christian Religious Studies' => ['CR', ['WAEC' => null, 'NECO' => null, 'JAMB' => null, 'Post-UTME' => null]],
            'Civic Education'       => ['Cv', ['WAEC' => null, 'NECO' => null]],
            'Computer Studies'      => ['CS', ['WAEC' => null, 'NECO' => null]],
            'General Studies'       => ['GS', ['Post-UTME' => null]],
            'Computer Science'      => ['Cp', ['IGCSE' => null]],
            'Business Studies'      => ['Bu', ['IGCSE' => null]],
            'History'               => ['Hi', ['IGCSE' => null]],
        ];

        $examModels = [];
        foreach ($exams as $i => $name) {
            $exam = Exam::firstOrNew(['slug' => Str::slug($name)]);
            if (! $exam->exists) {
                $exam->fill(['name' => $name, 'is_active' => true, 'sort_order' => $i + 1])->save();
            }
            $examModels[$name] = $exam;
        }

        foreach ($subjects as $name => [$code, $offered]) {
            $subject = Subject::firstOrNew(['slug' => Str::slug($name)]);
            if (! $subject->exists) {
                $subject->fill(['name' => $name, 'code' => $code, 'is_active' => true])->save();
            }

            foreach ($offered as $examName => $displayName) {
                // Only attach what is missing, so display names, prices and free counts set in the console survive a re-run.
                if (! $subject->exams()->whereKey($examModels[$examName]->id)->exists()) {
                    $subject->exams()->attach($examModels[$examName]->id, ['display_name' => $displayName]);
                }
            }
        }
    }
}
