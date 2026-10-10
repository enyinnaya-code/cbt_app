<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Services\DailyChallenge;
use App\Services\Engagement;
use App\Services\Leaderboard;
use App\Services\QuestionSelector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChallengeController extends Controller
{
    /** Today's challenge, run in the same runner as practice with answers shown straight away. */
    public function show(Request $request, QuestionSelector $selector)
    {
        $user = $request->user();

        // Optional: the challenge feeds the rankings, so it opens only for students who chose a nickname and accepted the policy.
        if (! Leaderboard::isParticipant($user)) {
            return redirect()->route('rankings.join');
        }

        $challenge = (new DailyChallenge($user, $selector))->today();

        if (! $challenge) {
            return redirect()->route('practice.index')->with('error', 'There are no questions for a challenge yet.');
        }

        $picked = $selector->byIds($challenge->ids, true);

        $bookmarked = DB::table('bookmarks')->where('user_id', $user->id)->where('is_bookmarked', true)
            ->whereIn('question_id', $challenge->ids)->pluck('question_id')->flip();
        foreach ($picked['questions'] as &$q) {
            $q['bookmarked'] = $bookmarked->has($q['id']);
        }
        unset($q);

        return view('student.practice-session', [
            'title' => 'Daily challenge',
            'session' => [
                'kind' => 'challenge',
                'title' => 'Daily challenge',
                'subtitle' => $challenge->subject,
                'mode' => 'instant',
                'lang' => $user->explanation_language ?: 'en',
                'questions' => $picked['questions'],
                'passages' => (object) $picked['passages'],
                'bonus' => $challenge->xp,
                'alreadyDone' => $challenge->done,
                'endpoints' => [
                    'attempts' => route('practice.attempts'),
                    'toggle' => route('saved.toggle'),
                    'complete' => route('challenge.complete'),
                    'exit' => route('dashboard'),
                    'home' => route('dashboard'),
                ],
            ],
        ]);
    }

    /** Called when the runner finishes. Pays the bonus once, and returns the new level and any new badges. */
    public function complete(Request $request, QuestionSelector $selector): JsonResponse
    {
        $user = $request->user();

        if (! Leaderboard::isParticipant($user)) {
            return response()->json(['done' => false, 'opt_in_required' => true], 403);
        }

        $result = (new DailyChallenge($user, $selector))->complete();

        if (! $result) {
            return response()->json(['done' => false], 404);
        }

        $engagement = new Engagement($user);
        $newBadges = collect($engagement->award())->map(fn ($key) => ['key' => $key] + Engagement::BADGES[$key])->values();

        return response()->json($result + ['level' => $engagement->level(), 'badges' => $newBadges]);
    }
}
