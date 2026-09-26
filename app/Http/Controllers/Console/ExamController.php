<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Subject;
use App\Services\MockService;
use App\Services\PackBuilder;
use App\Services\Pricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Exams, the subjects each offers, and what a subject costs. Admins only (see routes). */
class ExamController extends Controller
{
    public function index()
    {
        return view('console.exams.index', [
            'exams' => Exam::orderBy('sort_order')->orderBy('name')->withCount(['subjects', 'papers'])->get(),
            'defaults' => ['price' => Pricing::defaultPrice(), 'free' => Pricing::defaultFreeQuestions()],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', Rule::unique('exams', 'name')]]);
        $slug = Str::slug($data['name']);

        if ($slug === '' || Exam::where('slug', $slug)->exists()) {
            return back()->withInput()->with('error', 'There is already an exam with a very similar name.');
        }

        $exam = Exam::create(['name' => trim($data['name']), 'slug' => $slug, 'is_active' => true, 'sort_order' => (int) Exam::max('sort_order') + 1]);

        return redirect()->route('console.exams.edit', $exam)->with('success', "Added {$exam->name}. Now choose its subjects.");
    }

    public function edit(Exam $exam)
    {
        $attached = $exam->subjects()->orderBy('subjects.name')->get();

        return view('console.exams.edit', [
            'exam' => $exam,
            'attached' => $attached,
            'available' => Subject::whereNotIn('id', $attached->pluck('id'))->orderBy('name')->get(['id', 'name']),
            'defaults' => ['price' => Pricing::defaultPrice(), 'free' => Pricing::defaultFreeQuestions()],
            'format' => app(MockService::class)->format($exam),
            'customFormat' => is_array($exam->mock_format) && $exam->mock_format !== [],
            'deletable' => ! $exam->papers()->exists() && ! \App\Models\Order::where('exam_id', $exam->id)->exists(),
        ]);
    }

    /** Name, visibility, bundle price, and each subject's display name, price and free questions, in one save. */
    public function update(Request $request, Exam $exam, PackBuilder $packs)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('exams', 'name')->ignore($exam->id)],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'bundle_price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'subjects' => ['nullable', 'array'],
            'subjects.*.display_name' => ['nullable', 'string', 'max:80'],
            'subjects.*.price' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'subjects.*.free_questions' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'subjects.*.mock_questions' => ['nullable', 'integer', 'min:5', 'max:200'],
            'mock_custom' => ['nullable', 'boolean'],
            'mock_label' => ['nullable', 'string', 'max:60'],
            'mock_subject_count' => ['required_if:mock_custom,1', 'nullable', 'integer', 'min:1', 'max:8'],
            'mock_compulsory' => ['nullable', 'string', 'max:100'],
            'mock_questions' => ['required_if:mock_custom,1', 'nullable', 'integer', 'min:5', 'max:200'],
            'mock_minutes' => ['required_if:mock_custom,1', 'nullable', 'integer', 'min:5', 'max:300'],
        ], [
            '*.integer' => 'Prices and counts must be whole numbers, for example 1500.',
            '*.min' => 'Prices and counts cannot be negative, and a mock exam needs at least 5 questions and 5 minutes.',
            'mock_subject_count.required_if' => 'Say how many subjects one mock exam has.',
            'mock_questions.required_if' => 'Say how many questions each subject has in a mock exam.',
            'mock_minutes.required_if' => 'Say how long a mock exam lasts.',
        ]);

        $before = $exam->subjects()->get()->mapWithKeys(fn ($s) => [$s->id => [$s->pivot->price, $s->pivot->free_questions]])->all();

        DB::transaction(function () use ($exam, $data) {
            // The slug never changes: the mobile app and offline packs identify the exam by it.
            $exam->update([
                'name' => trim($data['name']),
                'is_active' => (bool) ($data['is_active'] ?? false),
                'sort_order' => $data['sort_order'] ?? $exam->sort_order,
                'bundle_price' => $data['bundle_price'] ?? null,
                'mock_format' => $this->mockFormat($exam, $data),
            ]);

            $attached = $exam->subjects()->pluck('subjects.id')->flip();

            foreach ($data['subjects'] ?? [] as $subjectId => $row) {
                if (! $attached->has((int) $subjectId)) { continue; }   // only subjects this exam really has

                $exam->subjects()->updateExistingPivot((int) $subjectId, [
                    'display_name' => filled($row['display_name'] ?? null) ? trim($row['display_name']) : null,
                    'price' => ($row['price'] ?? '') === '' ? null : (int) $row['price'],
                    'free_questions' => ($row['free_questions'] ?? '') === '' ? null : (int) $row['free_questions'],
                ]);
            }
        });

        // The free sample that goes into the offline packs follows the price and the free-question count.
        foreach ($exam->subjects()->get() as $subject) {
            if ($before[$subject->id] !== [$subject->pivot->price, $subject->pivot->free_questions]) {
                $packs->buildFree($exam, $subject);
            }
        }

        return back()->with('success', 'Saved.');
    }

    /** null means "use the standard format" (the built-in one for WAEC, NECO and JAMB, otherwise one subject of 50 questions in an hour). */
    private function mockFormat(Exam $exam, array $data): ?array
    {
        if (empty($data['mock_custom'])) { return null; }

        $slugs = $exam->subjects()->pluck('subjects.slug', 'subjects.id');
        $perSubject = [];
        foreach ($data['subjects'] ?? [] as $subjectId => $row) {
            if (isset($slugs[(int) $subjectId]) && ($row['mock_questions'] ?? '') !== '') {
                $perSubject[$slugs[(int) $subjectId]] = (int) $row['mock_questions'];
            }
        }

        $compulsory = ! empty($data['mock_compulsory']) && $slugs->contains($data['mock_compulsory']) ? $data['mock_compulsory'] : null;

        return MockService::makeFormat(
            filled($data['mock_label'] ?? null) ? trim($data['mock_label']) : $exam->name . ' mock',
            (int) $data['mock_subject_count'], $compulsory, (int) $data['mock_questions'], $perSubject, (int) $data['mock_minutes'],
        );
    }

    /** An exam can be deleted only while nothing depends on it: no papers, and nobody has paid for it. */
    public function destroy(Exam $exam)
    {
        if ($exam->papers()->exists()) {
            return back()->with('error', "{$exam->name} still has papers. Delete or move them first.");
        }
        if (\App\Models\Order::where('exam_id', $exam->id)->exists()) {
            return back()->with('error', "Students have ordered {$exam->name}, so it cannot be deleted. Hide it instead.");
        }

        $name = $exam->name;
        $exam->delete();

        return redirect()->route('console.exams.index')->with('success', "Deleted {$name}.");
    }

    public function attach(Request $request, Exam $exam)
    {
        $data = $request->validate(['subject_id' => ['required', 'exists:subjects,id']]);

        $exam->subjects()->syncWithoutDetaching([$data['subject_id']]);

        return back()->with('success', 'Subject added.');
    }

    /** Removing a subject from an exam is refused while papers exist for it: those questions would vanish from practice. */
    public function detach(Exam $exam, Subject $subject)
    {
        $papers = $exam->papers()->where('subject_id', $subject->id)->count();

        if ($papers > 0) {
            return back()->with('error', "{$subject->name} has {$papers} " . Str::plural('paper', $papers) . " under {$exam->name}. Delete or move them first.");
        }

        $exam->subjects()->detach($subject->id);

        return back()->with('success', 'Subject removed.');
    }

    public function storeSubject(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('subjects', 'name')],
            'code' => ['nullable', 'string', 'max:4'],
            'exam_id' => ['nullable', 'exists:exams,id'],
        ]);

        $slug = Str::slug($data['name']);
        if ($slug === '' || Subject::where('slug', $slug)->exists()) {
            return back()->withInput()->with('error', 'There is already a subject with a very similar name.');
        }

        $subject = Subject::create([
            'name' => trim($data['name']), 'slug' => $slug, 'is_active' => true,
            'code' => filled($data['code'] ?? null) ? trim($data['code']) : Str::of($data['name'])->substr(0, 2)->title()->toString(),
        ]);

        if (! empty($data['exam_id'])) {
            $subject->exams()->attach($data['exam_id']);
        }

        return back()->with('success', "Added {$subject->name}.");
    }
}
