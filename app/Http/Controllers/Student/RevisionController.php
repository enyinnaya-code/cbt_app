<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\QuestionSelector;
use App\Services\Revision;
use App\Services\StudentStats;
use Illuminate\Http\Request;

class RevisionController extends Controller
{
    public function __invoke(Request $request, QuestionSelector $selector)
    {
        $user = $request->user();
        $revision = new Revision($user, $selector);
        $stats = new StudentStats($user);

        return view('student.revision', [
            'sources' => Revision::SOURCES,
            'counts' => $revision->counts(),
            'weakTopics' => $stats->weakTopics(5),
            'plan' => $revision->plan($stats),
        ]);
    }
}
