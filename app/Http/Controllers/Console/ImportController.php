<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Paper;
use App\Services\QuestionImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportController extends Controller
{
    public function create(Request $request, Paper $paper)
    {
        abort_unless($paper->isEditableBy($request->user()), 403);

        return view('console.papers.import', ['paper' => $paper->load(['exam', 'subject'])]);
    }

    public function store(Request $request, Paper $paper, QuestionImporter $importer)
    {
        abort_unless($paper->isEditableBy($request->user()), 403);

        $request->validate(['file' => ['required', 'file', 'max:2048', 'mimes:csv,txt']]);

        $result = $importer->parse($request->file('file')->getRealPath(), $paper);

        if ($result['errors']) {
            return back()->with('import_errors', array_slice($result['errors'], 0, 30, true))
                ->with('import_error_total', count($result['errors']));
        }

        DB::transaction(function () use ($paper, $result) {
            foreach ($result['rows'] as $attrs) {
                $paper->questions()->create($attrs);
            }
        });

        $count = collect($result['rows'])->where('not_question', 0)->count();

        return redirect()->route('console.papers.show', $paper)->with('success', "Imported {$count} " . \Illuminate\Support\Str::plural('question', $count) . '.');
    }

    public function sample()
    {
        return response(QuestionImporter::sample(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="testacbt-questions-sample.csv"',
        ]);
    }
}
