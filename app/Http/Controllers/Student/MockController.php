<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\MockRun;
use App\Services\Access;
use App\Services\MockService;
use App\Services\QuestionSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MockController extends Controller
{
    public function __construct(private MockService $mocks, private QuestionSelector $selector) {}

    /** Setup: exam and subjects. Also offers to resume an exam that is still running. */
    public function index(Request $request)
    {
        $user = $request->user();

        $active = $this->mocks->active($user);
        if ($active && $active->isExpired()) {
            $this->mocks->submit($active);   // time ran out while they were away: mark what they had saved
            return redirect()->route('mock.result', $active);
        }

        $exams = Exam::where('is_active', true)->orderBy('sort_order')->get();
        $exam = $exams->firstWhere('slug', $request->query('exam'))
            ?? $exams->firstWhere('slug', ($user->preferred_exams ?? [])[0] ?? null)
            ?? $exams->first();

        return view('student.mock', [
            'exams' => $exams,
            'exam' => $exam,
            'format' => $exam ? $this->mocks->format($exam) : null,
            'options' => $exam ? $this->mocks->subjectOptions($exam, Access::for($user)) : collect(),
            'active' => $active,
            'recent' => MockRun::where('user_id', $user->id)->whereNotNull('submitted_at')->with('exam:id,name')->latest('id')->limit(3)->get(),
        ]);
    }

    public function start(Request $request)
    {
        $data = $request->validate([
            'exam' => ['required', Rule::exists('exams', 'slug')],
            'subjects' => ['required', 'array', 'max:6'],
            'subjects.*' => ['string'],
        ]);

        if ($active = $this->mocks->active($request->user())) {
            return redirect()->route('mock.show', $active)->with('error', 'You already have a mock exam in progress. Finish it first.');
        }

        $exam = Exam::where('slug', $data['exam'])->firstOrFail();
        $run = $this->mocks->start($request->user(), $exam, $data['subjects']);

        return redirect()->route('mock.show', $run);
    }

    /** The exam itself. Same paper, same clock, every time it is opened. */
    public function show(Request $request, MockRun $run)
    {
        $this->authorizeRun($request, $run);

        if ($run->isSubmitted()) { return redirect()->route('mock.result', $run); }
        if ($run->isExpired()) {
            $this->mocks->submit($run);
            return redirect()->route('mock.result', $run)->with('success', 'Time was up, so your saved answers were marked.');
        }

        $paper = $this->selector->byIds($run->question_ids, withKey: false);

        return view('student.mock-run', [
            'title' => $this->mocks->format($run->exam)['label'] ?? 'Mock exam',
            'session' => [
                'kind' => 'mock',
                'title' => $this->mocks->format($run->exam)['label'] ?? 'Mock exam',
                'subtitle' => count($run->groups) > 1 ? count($run->groups) . ' subjects' : $run->groups[0]['name'],
                'questions' => $paper['questions'],
                'passages' => (object) $paper['passages'],
                'groups' => $run->groups,
                'answers' => (object) ($run->answers ?? []),
                'flagged' => $run->flagged ?? [],
                'position' => $run->position,
                'deadline' => $run->deadline_at->toIso8601String(),
                'server_now' => now()->toIso8601String(),
                'saved_at' => $run->updated_at->toIso8601String(),
                'endpoints' => [
                    'save' => route('mock.save', $run),
                    'submit' => route('mock.submit', $run),
                    'result' => route('mock.result', $run),
                    'exit' => route('mock.index'),
                ],
            ],
        ]);
    }

    public function save(Request $request, MockRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        $data = $request->validate([
            'answers' => ['present', 'array', 'max:400'],
            'flagged' => ['present', 'array', 'max:400'],
            'position' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $this->mocks->save($run, $data['answers'], $data['flagged'], $data['position']);

        return response()->json(['saved' => true, 'submitted' => $run->fresh()->isSubmitted()]);
    }

    public function submit(Request $request, MockRun $run): JsonResponse
    {
        $this->authorizeRun($request, $run);

        $data = $request->validate([
            'answers' => ['present', 'array', 'max:400'],
        ]);

        $this->mocks->submit($run, $data['answers']);

        return response()->json(['submitted' => true, 'result' => route('mock.result', $run)]);
    }

    public function result(Request $request, MockRun $run)
    {
        $this->authorizeRun($request, $run);

        if (! $run->isSubmitted()) { return redirect()->route('mock.show', $run); }

        $result = $run->result;
        $strong = config('testacbt.strong_accuracy');

        return view('student.mock-result', [
            'run' => $run,
            'label' => $this->mocks->format($run->exam)['label'] ?? 'Mock exam',
            'result' => $result,
            'change' => $this->mocks->changeSincePrevious($run),
            'weak' => collect($result['topics'] ?? [])->filter(fn ($t) => $t['total'] >= 2 && $t['correct'] / $t['total'] * 100 < $strong)
                ->sortBy(fn ($t) => $t['correct'] / $t['total'])->take(6)->values(),
            'strong' => $strong,
        ]);
    }

    /** Every question with the student's answer, the right answer and the explanation. Only after submitting. */
    public function review(Request $request, MockRun $run)
    {
        $this->authorizeRun($request, $run);
        abort_unless($run->isSubmitted(), 404);

        $paper = $this->selector->byIds($run->question_ids, withKey: true);
        $byId = collect($paper['questions'])->keyBy('id');

        $offset = 0;
        $sections = [];
        foreach ($run->groups as $g) {
            $sections[] = [
                'name' => $g['name'],
                'questions' => collect(array_slice($run->question_ids, $offset, $g['count']))->map(fn ($id) => $byId->get($id))->filter()->values(),
            ];
            $offset += $g['count'];
        }

        return view('student.mock-review', [
            'run' => $run,
            'sections' => $sections,
            'passages' => $paper['passages'],
            'answers' => $run->answers ?? [],
            'lang' => $request->user()->explanation_language ?: 'en',
        ]);
    }

    private function authorizeRun(Request $request, MockRun $run): void
    {
        abort_unless($run->user_id === $request->user()->id, 404);
    }
}
