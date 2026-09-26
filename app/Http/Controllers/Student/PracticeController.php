<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Paper;
use App\Models\Question;
use App\Models\Subject;
use App\Services\Access;
use App\Services\Pricing;
use App\Services\ProgressRecorder;
use App\Services\QuestionSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PracticeController extends Controller
{
    /** Setup: choose exam, subject, year, when to see answers and how many questions. */
    public function index(Request $request, QuestionSelector $selector)
    {
        $user = $request->user();
        $exams = Exam::where('is_active', true)->orderBy('sort_order')->get();

        $exam = $exams->firstWhere('slug', $request->query('exam'))
            ?? $exams->firstWhere('slug', ($user->preferred_exams ?? [])[0] ?? null)
            ?? $exams->first();

        $access = Access::for($user);
        $available = $exam ? $selector->availability($exam) : collect();

        $subjects = $exam
            ? $exam->subjects()->where('subjects.is_active', true)->orderBy('subjects.name')->get()
                ->map(function ($s) use ($available, $access, $exam) {
                    $s->total = (int) ($available[$s->id] ?? 0);
                    $s->locked = ! $access->full($exam->id, $s->id);
                    // Until it is unlocked, a subject offers only its free sample.
                    $s->available = $s->locked ? min($s->total, $access->freeLimit($exam->id, $s->id)) : $s->total;
                    $s->price = $access->price($exam->id, $s->id);
                    $s->expires = $access->expiresAt($exam->id, $s->id);
                    $s->label = $s->pivot->display_name ?: $s->name;
                    return $s;
                })->filter(fn ($s) => $s->total > 0)->values()
            : collect();

        $subject = $subjects->firstWhere('slug', $request->query('subject'));

        // A locked subject can only be practised from its free sample, so offer only the years and topics found in it.
        $freeIds = $exam && $subject && $subject->locked ? array_values($access->freeIds($exam->id, $subject->id)) : null;
        $years = $exam && $subject
            ? ($freeIds === null ? $selector->years($exam, $subject) : Paper::whereHas('questions', fn ($q) => $q->whereIn('id', $freeIds ?: [0]))->orderByDesc('year')->pluck('year')->unique()->values())
            : collect();
        $topics = $subject
            ? $subject->topics()->when($freeIds !== null, fn ($q) => $q->whereIn('id', Question::whereIn('id', $freeIds ?: [0])->whereNotNull('topic_id')->pluck('topic_id')))->orderBy('name')->get(['id', 'name'])
            : collect();

        return view('student.practice', [
            'exams' => $exams,
            'exam' => $exam,
            'subjects' => $subjects,
            'subject' => $subject,
            'years' => $years,
            'counts' => config('testacbt.practice_counts'),
            'topics' => $topics,
            'unlock' => $exam && $subject && $subject->locked ? route('checkout.show', ['exam' => $exam->slug, 'subjects' => [$subject->slug]]) : null,
        ]);
    }

    /** The practice runner. All the questions are sent up front so it works without waiting on the network. */
    public function session(Request $request, QuestionSelector $selector)
    {
        $data = $request->validate([
            'saved' => ['nullable', 'boolean'],
            'exam' => ['required_without_all:saved,topic', 'nullable', Rule::exists('exams', 'slug')],
            'subject' => ['required_without:saved', 'nullable', Rule::exists('subjects', 'slug')],
            'year' => ['nullable', 'integer', 'between:1970,2100'],
            'topic' => ['nullable', 'integer'],
            'mode' => ['required', 'in:instant,end'],
            'count' => ['required', 'integer', 'between:5,50'],
        ]);

        $user = $request->user();
        $count = (int) $data['count'];

        if (! empty($data['saved'])) {
            $picked = $selector->saved($user, $count, null, Access::for($user));
            $title = 'Saved questions';
            $subtitle = null;
            $again = route('saved');
        } else {
            $exam = ! empty($data['exam']) ? Exam::where('slug', $data['exam'])->firstOrFail() : null;
            $subject = Subject::where('slug', $data['subject'])->firstOrFail();
            $topic = ! empty($data['topic']) ? \App\Models\Topic::where('subject_id', $subject->id)->find($data['topic']) : null;
            abort_if(! empty($data['topic']) && ! $topic, 404);

            $picked = $selector->practice($exam, $subject, $data['year'] ?? null, $count, $topic?->id, null, Access::for($user));
            // JAMB calls English "Use of English"; show each exam's own name for the subject.
            $title = ($exam?->subjects()->whereKey($subject->id)->first()?->pivot->display_name) ?: $subject->name;
            $subtitle = $topic
                ? "Topic: {$topic->name}"
                : $exam->name . (! empty($data['year']) ? ' ' . $data['year'] : '');
            $again = route('practice.index', array_filter(['exam' => $exam?->slug, 'subject' => $subject->slug]));
        }

        if (! $picked['questions']) {
            return redirect()->route('practice.index', array_filter(['exam' => $data['exam'] ?? null, 'subject' => $data['subject'] ?? null]))
                ->with('error', 'There are no questions for that choice yet. Try another year or subject.');
        }

        $bookmarked = DB::table('bookmarks')->where('user_id', $user->id)->where('is_bookmarked', true)
            ->whereIn('question_id', array_column($picked['questions'], 'id'))->pluck('question_id')->flip();

        foreach ($picked['questions'] as &$q) {
            $q['bookmarked'] = $bookmarked->has($q['id']);
        }
        unset($q);

        return view('student.practice-session', [
            'title' => $title,
            'session' => [
                'kind' => 'practice',
                'title' => $title,
                'subtitle' => $subtitle,
                'mode' => $data['mode'],
                'lang' => $user->explanation_language ?: 'en',
                'questions' => $picked['questions'],
                'passages' => (object) $picked['passages'],
                'endpoints' => [
                    'attempts' => route('practice.attempts'),
                    'toggle' => route('saved.toggle'),
                    'exit' => $again,
                    'home' => route('dashboard'),
                ],
            ],
        ]);
    }

    /** Receives the answers the runner sends as the student goes. Same rules as the mobile app's sync. */
    public function attempts(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attempts' => ['required', 'array', 'max:100'],
            'attempts.*.client_uuid' => ['required', 'uuid'],
            'attempts.*.question_id' => ['required', 'integer'],
            'attempts.*.mode' => ['required', 'in:practice,mock'],
            'attempts.*.selected' => ['nullable', 'string', 'in:A,B,C,D,E,a,b,c,d,e'],
            'attempts.*.time_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'attempts.*.answered_at' => ['required', 'date'],
        ]);

        $result = (new ProgressRecorder($request->user()))->attempts($data['attempts']);

        return response()->json($result);
    }
}
