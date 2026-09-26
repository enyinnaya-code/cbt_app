<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\StudentStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProgressController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $stats = new StudentStats($user);

        $subjects = $stats->subjects();
        $days = $stats->lastDays(7);
        $total = $stats->totalAnswered();
        $correct = (int) DB::table('question_attempts')->where('user_id', $user->id)->where('is_correct', true)->count();
        $thisWeek = array_sum(array_column($days, 'count'));

        $mocks = DB::table('mock_sessions as m')
            ->join('exams as e', 'e.id', '=', 'm.exam_id')
            ->where('m.user_id', $user->id)
            ->orderByDesc('m.taken_at')->limit(5)
            ->get(['e.name as exam', 'm.score', 'm.total', 'm.duration_seconds', 'm.taken_at']);

        $strong = config('testacbt.strong_accuracy');

        return view('student.progress', [
            'total' => $total,
            'accuracy' => $total ? (int) round($correct / $total * 100) : null,
            'streak' => $stats->streak(),
            'days' => $days,
            'thisWeek' => $thisWeek,
            'maxDay' => max(1, max(array_column($days, 'count'))),
            'subjects' => $subjects,
            'strongest' => $subjects->where('accuracy', '>=', $strong)->sortByDesc('accuracy')->first() ?? $subjects->sortByDesc('accuracy')->first(),
            'weakest' => $subjects->first(),
            'weakTopics' => $stats->weakTopics(),
            'mocks' => $mocks,
        ]);
    }
}
