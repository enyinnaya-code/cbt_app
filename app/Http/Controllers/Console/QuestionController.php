<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Topic;
use App\Support\HtmlCleaner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class QuestionController extends Controller
{
    private const LETTERS = ['A', 'B', 'C', 'D', 'E'];

    public function create(Request $request, Paper $paper)
    {
        abort_unless($paper->isEditableBy($request->user()), 403);

        return view('console.questions.form', [
            'paper' => $paper->load(['exam', 'subject']),
            'question' => new Question(['not_question' => $request->query('type') === 'passage' ? 1 : 0, 'mark' => 1]),
            'topics' => $this->topics($paper),
            'options' => [],
        ]);
    }

    public function store(Request $request, Paper $paper)
    {
        abort_unless($paper->isEditableBy($request->user()), 403);

        $attrs = $this->attributes($request, $paper);
        $question = $paper->questions()->create($attrs);

        $more = $request->boolean('add_another');

        return redirect($more
            ? route('console.questions.create', [$paper, 'type' => $question->not_question ? 'passage' : 'question'])
            : route('console.papers.show', $paper) . '#q' . $question->id)
            ->with('success', $question->not_question ? 'Passage added.' : 'Question added.');
    }

    public function edit(Request $request, Question $question)
    {
        $paper = $this->paperOf($question);
        abort_unless($paper->isEditableBy($request->user()), 403);

        $options = is_array($question->options) ? $question->options : (json_decode((string) $question->options, true) ?: []);

        return view('console.questions.form', [
            'paper' => $paper->load(['exam', 'subject']),
            'question' => $question,
            'topics' => $this->topics($paper),
            'options' => $options,
        ]);
    }

    public function update(Request $request, Question $question)
    {
        $paper = $this->paperOf($question);
        abort_unless($paper->isEditableBy($request->user()), 403);

        $question->update($this->attributes($request, $paper, $question));

        return redirect(route('console.papers.show', $paper) . '#q' . $question->id)->with('success', 'Saved.');
    }

    public function destroy(Request $request, Question $question)
    {
        $paper = $this->paperOf($question);
        abort_unless($paper->isEditableBy($request->user()), 403);

        // Deleting a question also deletes students' recorded answers to it, so say when that will happen.
        $answered = DB::table('question_attempts')->where('question_id', $question->id)->count();
        $question->delete();

        return redirect()->route('console.papers.show', $paper)->with('success', $answered
            ? "Deleted. {$answered} student " . Str::plural('answer', $answered) . ' to it were removed too.'
            : 'Deleted.');
    }

    // ---------------------------------------------------------------------------------------------

    private function paperOf(Question $question): Paper
    {
        return Paper::findOrFail($question->paper_id);   // legacy school questions without a paper are not editable here
    }

    private function topics(Paper $paper)
    {
        return Topic::where('subject_id', $paper->subject_id)->orderBy('name')->get(['id', 'name']);
    }

    /** Validates the form and returns clean column values. Every piece of HTML goes through the sanitiser. */
    private function attributes(Request $request, Paper $paper, ?Question $existing = null): array
    {
        $isPassage = $request->input('type') === 'passage';

        $rules = [
            'type' => ['required', 'in:question,passage'],
            'question' => ['required', 'string', 'max:60000'],
        ];
        if (! $isPassage) {
            $rules += [
                'options' => ['required', 'array'],
                'options.*' => ['nullable', 'string', 'max:5000'],
                'answer' => ['required', Rule::in(self::LETTERS)],
                'mark' => ['nullable', 'integer', 'between:1,20'],
                'topic_id' => ['nullable', Rule::exists('topics', 'id')->where('subject_id', $paper->subject_id)],
                'explanation_en' => ['nullable', 'string', 'max:20000'],
                'explanation_pcm' => ['nullable', 'string', 'max:20000'],
            ];
        }

        $data = $request->validate($rules);

        $html = HtmlCleaner::clean($data['question']);
        if (! $this->hasContent($html)) {
            throw ValidationException::withMessages(['question' => 'The question text cannot be empty.']);
        }

        if ($isPassage) {
            return ['not_question' => 1, 'question' => $html, 'options' => null, 'answer' => null, 'mark' => null,
                'topic_id' => null, 'explanation_en' => null, 'explanation_pcm' => null];
        }

        $options = [];
        foreach (self::LETTERS as $letter) {
            $clean = HtmlCleaner::clean($data['options'][$letter] ?? '');
            if ($this->hasContent($clean)) { $options[$letter] = $clean; }
        }

        if (count($options) < 2) {
            throw ValidationException::withMessages(['options' => 'Give at least two answer options.']);
        }
        if (! isset($options[$data['answer']])) {
            throw ValidationException::withMessages(['answer' => 'The correct answer must be one of the options you filled in.']);
        }

        return [
            'not_question' => 0,
            'question' => $html,
            'options' => json_encode($options, JSON_UNESCAPED_UNICODE),   // the legacy column holds a JSON string
            'answer' => $data['answer'],
            'mark' => (int) ($data['mark'] ?? 1),
            'topic_id' => $data['topic_id'] ?? null,
            'explanation_en' => $this->optional($data['explanation_en'] ?? null),
            'explanation_pcm' => $this->optional($data['explanation_pcm'] ?? null),
        ];
    }

    private function optional(?string $html): ?string
    {
        $clean = HtmlCleaner::clean($html);

        return $this->hasContent($clean) ? $clean : null;
    }

    /** Text, or an image (an image-only option is a real answer in maths and diagram questions). */
    private function hasContent(string $html): bool
    {
        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'), " \t\n\r\0\x0B\xC2\xA0") !== '' || str_contains($html, '<img');
    }
}
