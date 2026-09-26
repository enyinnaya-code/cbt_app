<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Test;
use App\Services\LegacyTagger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Old school tests that have not been turned into exam papers yet. Admin only, because it moves questions.
 */
class LegacyController extends Controller
{
    public function index(LegacyTagger $tagger)
    {
        return view('console.legacy', [
            'tests' => $tagger->untagged(),
            'exams' => Exam::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']),
            'subjects' => Subject::where('is_active', true)->with('exams:id')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Test $test, LegacyTagger $tagger)
    {
        $data = $request->validate([
            'exam_id' => ['required', Rule::exists('exams', 'id')],
            'subject_id' => ['required', Rule::exists('subjects', 'id')],
            'year' => ['required', 'integer', 'between:' . LegacyTagger::FIRST_YEAR . ',' . ((int) date('Y') + 1)],
        ]);

        if (! Question::where('test_id', $test->id)->exists()) {
            return back()->with('error', 'That old test has no questions, so there is nothing to move.');
        }

        $result = $tagger->tag($test, Exam::findOrFail($data['exam_id']), Subject::findOrFail($data['subject_id']), (int) $data['year']);

        return redirect()->route('console.papers.show', $result['paper'])
            ->with('success', "Created a draft paper with {$result['moved']} " . \Illuminate\Support\Str::plural('question', $result['moved']) . '. Check it, then publish it.');
    }
}
