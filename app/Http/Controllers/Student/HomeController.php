<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\DailyChallenge;
use App\Services\Engagement;
use App\Services\QuestionSelector;
use App\Services\StudentStats;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $stats = new StudentStats($user);
        $engagement = new Engagement($user);

        // Completing a challenge on the phone or an old browser tab may leave a bonus unpaid until now. The challenge is
        // opt-in (it feeds the rankings), so students who have not joined see an invitation instead.
        $joined = \App\Services\Leaderboard::isParticipant($user);
        $challenge = new DailyChallenge($user, app(QuestionSelector::class));
        $joined && $challenge->complete();
        $engagement->award();
        $weakTopic = $stats->weakTopics(1)->first();
        $weakSubject = $stats->subjects()->first(fn ($s) => $s->answered >= 5 && $s->accuracy < config('testacbt.strong_accuracy'));

        $hour = (int) now()->format('G');
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

        return view('student.home', [
            'greeting' => $greeting,
            'firstName' => explode(' ', trim($user->name))[0],
            'streak' => $stats->streak(),
            'daysToExam' => $stats->daysToExam(),
            'targetExam' => $user->target_exam ? Exam::where('slug', $user->target_exam)->first() : null,
            'last' => $stats->lastPractised(),
            'subjects' => $stats->subjects(),
            'answered' => $stats->totalAnswered(),
            'level' => $engagement->level(),
            'xpToday' => $engagement->xpToday(),
            'joined' => $joined,
            'challenge' => $joined ? $challenge->today() : null,
            'nickname' => $user->nickname,
            'badges' => $engagement->badges(),
            'message' => $engagement->message($stats),
            'weakTopic' => $weakTopic,
            'weakSubject' => $weakSubject,
        ]);
    }
}
