<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the exam bodies and the subjects each one offers. Safe to re-run.
 */
class ExamCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $exams = ['WAEC', 'NECO', 'JAMB'];

        // subject => [code, [exam => display name or null]]
        $subjects = [
            'English Language'      => ['En', ['WAEC' => null, 'NECO' => null, 'JAMB' => 'Use of English']],
            'Mathematics'           => ['Ma', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Physics'               => ['Ph', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Chemistry'             => ['Ch', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Biology'               => ['Bi', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Economics'             => ['Ec', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Government'            => ['Go', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Literature in English' => ['Li', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Commerce'              => ['Co', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Financial Accounting'  => ['Ac', ['WAEC' => null, 'NECO' => null, 'JAMB' => 'Principles of Accounts']],
            'Geography'             => ['Ge', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Agricultural Science'  => ['Ag', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Christian Religious Studies' => ['CR', ['WAEC' => null, 'NECO' => null, 'JAMB' => null]],
            'Civic Education'       => ['Cv', ['WAEC' => null, 'NECO' => null]],
            'Computer Studies'      => ['CS', ['WAEC' => null, 'NECO' => null]],
        ];

        $examModels = [];
        foreach ($exams as $i => $name) {
            $examModels[$name] = Exam::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true, 'sort_order' => $i + 1]
            );
        }

        foreach ($subjects as $name => [$code, $offered]) {
            $subject = Subject::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'code' => $code, 'is_active' => true]
            );

            foreach ($offered as $examName => $displayName) {
                $subject->exams()->syncWithoutDetaching([
                    $examModels[$examName]->id => ['display_name' => $displayName],
                ]);
            }
        }
    }
}
