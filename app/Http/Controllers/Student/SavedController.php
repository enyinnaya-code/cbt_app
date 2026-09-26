<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\Access;
use App\Services\ProgressRecorder;
use App\Support\HtmlCleaner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SavedController extends Controller
{
    public function index(Request $request)
    {
        $access = Access::for($request->user());

        $all = DB::table('bookmarks as b')
            ->join('questions as q', 'q.id', '=', 'b.question_id')
            ->join('papers as p', 'p.id', '=', 'q.paper_id')
            ->join('exams as e', 'e.id', '=', 'p.exam_id')
            ->join('subjects as s', 's.id', '=', 'p.subject_id')
            ->leftJoin('exam_subject as es', fn ($j) => $j->on('es.exam_id', '=', 'p.exam_id')->on('es.subject_id', '=', 'p.subject_id'))
            ->where('b.user_id', $request->user()->id)->where('b.is_bookmarked', true)
            ->where('p.status', 'published')
            ->orderByDesc('b.changed_at')
            // Each exam has its own name for some subjects (JAMB: "Use of English").
            ->select('q.id', 'q.question', 'q.options', 'q.answer', 'q.explanation_en', 'q.explanation_pcm', 'p.exam_id', 'p.subject_id', 'e.name as exam', DB::raw('COALESCE(es.display_name, s.name) as subject'), 'p.year')
            ->get();

        // A saved question whose subject is no longer unlocked (a purchase ran out) stays saved but is hidden until renewed.
        $rows = $all->filter(fn ($r) => $access->allows($r->exam_id, $r->subject_id, $r->id))->values()
            ->map(function ($r) {
                $options = json_decode((string) $r->options, true) ?: [];
                $r->question = HtmlCleaner::clean($r->question);
                $r->options = array_map(fn ($o) => HtmlCleaner::clean((string) $o), array_filter($options, fn ($o) => $o !== null && $o !== ''));
                $r->answer = strtoupper(trim((string) $r->answer));
                $r->explanation_en = $r->explanation_en ? HtmlCleaner::clean($r->explanation_en) : null;
                $r->explanation_pcm = $r->explanation_pcm ? HtmlCleaner::clean($r->explanation_pcm) : null;
                return $r;
            });

        return view('student.saved', ['questions' => $rows, 'locked' => $all->count() - $rows->count(), 'lang' => $request->user()->explanation_language ?: 'en']);
    }

    /** Save or un-save a question. Works from the practice runner (JSON) and from the Saved page (form). */
    public function toggle(Request $request)
    {
        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'bookmarked' => ['required', 'boolean'],
        ]);

        $result = (new ProgressRecorder($request->user()))->bookmarks([[
            'question_id' => $data['question_id'],
            'bookmarked' => (bool) $data['bookmarked'],
            'changed_at' => now()->toIso8601String(),
        ]], authoritative: true);

        if ($request->expectsJson()) {
            return response()->json($result + ['bookmarked' => (bool) $data['bookmarked']]);
        }

        return back()->with('success', $data['bookmarked'] ? 'Question saved.' : 'Removed from saved.');
    }
}
