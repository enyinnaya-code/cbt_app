<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Paper;
use App\Models\Subject;
use App\Services\LegacyTagger;
use App\Services\PackBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaperController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'exam' => ['nullable', 'integer'], 'subject' => ['nullable', 'integer'],
            'status' => ['nullable', 'in:draft,published,archived'], 'year' => ['nullable', 'integer'], 'q' => ['nullable', 'string', 'max:80'],
        ]);

        $papers = Paper::query()->with(['exam:id,name', 'subject:id,name', 'creator:id,name'])
            ->withCount(['questions as question_count' => fn ($q) => $q->whereRaw('COALESCE(not_question, 0) = 0')])
            ->when($filters['exam'] ?? null, fn ($q, $v) => $q->where('exam_id', $v))
            ->when($filters['subject'] ?? null, fn ($q, $v) => $q->where('subject_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['year'] ?? null, fn ($q, $v) => $q->where('year', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where('title', 'like', '%' . addcslashes($v, '%_\\') . '%'))
            ->orderBy('exam_id')->orderBy('subject_id')->orderByDesc('year')->orderBy('id')
            ->paginate(20)->withQueryString();

        return view('console.papers.index', [
            'papers' => $papers,
            'filters' => $filters,
            'exams' => Exam::orderBy('sort_order')->get(['id', 'name']),
            'subjects' => Subject::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create()
    {
        return view('console.papers.form', ['paper' => new Paper(['year' => (int) date('Y') - 1]), 'exams' => $this->exams(), 'subjects' => Subject::where('is_active', true)->with('exams:id')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $paper = Paper::create($data + ['status' => Paper::DRAFT, 'created_by' => $request->user()->id]);

        return redirect()->route('console.papers.show', $paper)->with('success', 'Paper created as a draft. Add its questions below.');
    }

    public function show(Request $request, Paper $paper)
    {
        $paper->load(['exam', 'subject', 'creator:id,name']);

        $items = $paper->questions()->with('topic:id,name')->orderBy('id')->get();
        $number = 0;
        $items->each(function ($q) use (&$number) {
            $q->setAttribute('number', (int) $q->not_question === 1 ? null : ++$number);
            $opts = is_array($q->options) ? $q->options : json_decode((string) $q->options, true);
            $q->setAttribute('option_list', is_array($opts) ? array_filter($opts, fn ($o) => $o !== null && $o !== '') : []);
        });

        return view('console.papers.show', [
            'paper' => $paper,
            'items' => $items,
            'questionCount' => $number,
            'attempts' => DB::table('question_attempts')->whereIn('question_id', $paper->questions()->select('id'))->count(),
            'editable' => $paper->isEditableBy($request->user()),
            'exams' => $this->exams(),
            'subjects' => Subject::where('is_active', true)->with('exams:id')->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Paper $paper, PackBuilder $packs)
    {
        abort_unless($paper->isEditableBy($request->user()), 403);

        $old = [$paper->exam_id, $paper->subject_id];
        $paper->update($this->validated($request));

        // A live paper that moved to another exam, subject or year changes what students download.
        if ($paper->status === Paper::PUBLISHED) {
            $this->rebuild($packs, [$old, [$paper->exam_id, $paper->subject_id]]);
        }

        return back()->with('success', 'Paper details saved.');
    }

    public function destroy(Request $request, Paper $paper)
    {
        abort_unless($paper->isEditableBy($request->user()), 403);

        if ($paper->status === Paper::PUBLISHED) {
            return back()->with('error', 'Unpublish this paper before deleting it.');
        }
        if (DB::table('question_attempts')->whereIn('question_id', $paper->questions()->select('id'))->exists()) {
            return back()->with('error', 'Students have already answered questions from this paper, so it cannot be deleted. Leave it as a draft instead.');
        }

        $paper->delete();

        return redirect()->route('console.papers.index')->with('success', 'Paper deleted.');
    }

    /** Admin only: makes the paper visible to students and rebuilds the offline pack. */
    public function publish(Paper $paper, PackBuilder $packs)
    {
        $usable = $paper->questions()->whereRaw('COALESCE(not_question, 0) = 0')->whereNotNull('answer')->where('answer', '!=', '')->count();

        if ($usable === 0) {
            return back()->with('error', 'Add at least one question with an answer before publishing.');
        }

        $paper->update(['status' => Paper::PUBLISHED, 'published_at' => now()]);
        $note = $this->rebuild($packs, [[$paper->exam_id, $paper->subject_id]]);

        return back()->with('success', 'Published. ' . $note);
    }

    public function unpublish(Paper $paper, PackBuilder $packs)
    {
        $paper->update(['status' => Paper::DRAFT, 'published_at' => null]);
        $note = $this->rebuild($packs, [[$paper->exam_id, $paper->subject_id]]);

        return back()->with('success', 'Moved back to draft. ' . $note);
    }

    // ---------------------------------------------------------------------------------------------

    private function exams()
    {
        return Exam::where('is_active', true)->orderBy('sort_order')->get(['id', 'name']);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'exam_id' => ['required', Rule::exists('exams', 'id')],
            'subject_id' => ['required', Rule::exists('subjects', 'id')],
            'year' => ['required', 'integer', 'between:' . LegacyTagger::FIRST_YEAR . ',' . ((int) date('Y') + 1)],
            'title' => ['nullable', 'string', 'max:160'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,300'],
        ]);

        // The subject must be one that exam actually offers.
        if (! DB::table('exam_subject')->where('exam_id', $data['exam_id'])->where('subject_id', $data['subject_id'])->exists()) {
            throw ValidationException::withMessages(['subject_id' => 'That exam does not offer this subject.']);
        }

        $data['title'] = $data['title'] ?? null;

        return $data;
    }

    /**
     * @param  array<int, array{0:int,1:int}>  $pairs  [exam_id, subject_id] combinations to rebuild
     */
    private function rebuild(PackBuilder $packs, array $pairs): string
    {
        $notes = [];

        foreach (collect($pairs)->unique(fn ($p) => $p[0] . '-' . $p[1]) as [$examId, $subjectId]) {
            $exam = Exam::find($examId);
            $subject = Subject::find($subjectId);
            if (! $exam || ! $subject) { continue; }

            $r = $packs->build($exam, $subject);
            $notes[] = match ($r['status']) {
                'built' => "{$exam->name} {$subject->name} pack is now version {$r['pack']->version}.",
                'retired' => "{$exam->name} {$subject->name} has no published papers left, so its pack was removed.",
                default => '',
            };
        }

        return trim(implode(' ', array_filter($notes)));
    }
}
