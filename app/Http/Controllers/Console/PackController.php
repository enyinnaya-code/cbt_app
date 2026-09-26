<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\ContentPack;
use App\Models\Exam;
use App\Models\Paper;
use App\Models\Subject;
use App\Services\PackBuilder;
use Illuminate\Http\Request;

class PackController extends Controller
{
    /** Every exam + subject that has published papers or a current pack, and how the pack stands. */
    public function index()
    {
        $current = ContentPack::current()->tier(ContentPack::FULL)->get()->keyBy(fn ($p) => $p->exam_id . '-' . $p->subject_id);

        $published = Paper::published()->select('exam_id', 'subject_id')->distinct()->get()
            ->mapWithKeys(fn ($p) => [$p->exam_id . '-' . $p->subject_id => $p]);

        $keys = $current->keys()->merge($published->keys())->unique();

        $exams = Exam::pluck('name', 'id');
        $subjects = Subject::pluck('name', 'id');

        $rows = $keys->map(function ($key) use ($current, $exams, $subjects) {
            [$examId, $subjectId] = array_map('intval', explode('-', $key));
            $pack = $current->get($key);

            return (object) [
                'exam_id' => $examId, 'subject_id' => $subjectId,
                'exam' => $exams[$examId] ?? '?', 'subject' => $subjects[$subjectId] ?? '?',
                'pack' => $pack,
                // Published papers changed after the pack was built, or no pack yet.
                'stale' => ! $pack || Paper::published()->where('exam_id', $examId)->where('subject_id', $subjectId)
                    ->where('updated_at', '>', $pack->built_at)->exists(),
            ];
        })->sortBy(fn ($r) => $r->exam . ' ' . $r->subject)->values();

        return view('console.packs', ['rows' => $rows]);
    }

    /** Rebuild one pack (exam + subject) or all of them. */
    public function rebuild(Request $request, PackBuilder $builder)
    {
        $data = $request->validate(['exam_id' => ['nullable', 'integer'], 'subject_id' => ['nullable', 'integer']]);

        $pairs = ContentPack::current()->select('exam_id', 'subject_id')->get()
            ->concat(Paper::published()->select('exam_id', 'subject_id')->distinct()->get())
            ->unique(fn ($p) => $p->exam_id . '-' . $p->subject_id)
            ->when($data['exam_id'] ?? null, fn ($c, $v) => $c->where('exam_id', $v))
            ->when($data['subject_id'] ?? null, fn ($c, $v) => $c->where('subject_id', $v));

        $built = $unchanged = 0;
        $warnings = [];

        foreach ($pairs as $pair) {
            $exam = Exam::find($pair->exam_id);
            $subject = Subject::find($pair->subject_id);
            if (! $exam || ! $subject) { continue; }

            $r = $builder->build($exam, $subject);
            $r['status'] === 'unchanged' ? $unchanged++ : $built++;
            foreach ($r['warnings'] as $w) { $warnings[] = "{$exam->name} {$subject->name}: $w"; }
        }

        return back()->with('success', "{$built} pack(s) updated, {$unchanged} already up to date.")
            ->with('pack_warnings', $warnings);
    }
}
