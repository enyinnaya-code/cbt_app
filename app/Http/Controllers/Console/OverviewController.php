<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\ContentPack;
use App\Models\Paper;
use App\Models\Question;
use App\Models\User;
use App\Services\LegacyTagger;
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    public function index(Request $request, LegacyTagger $tagger)
    {
        $user = $request->user();

        $usableQuestions = Question::whereRaw('COALESCE(not_question, 0) = 0')->whereNotNull('paper_id')->whereNotNull('answer')->where('answer', '!=', '');

        return view('console.overview', [
            'papers' => Paper::selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status'),
            'questions' => (clone $usableQuestions)->count(),
            'liveQuestions' => (clone $usableQuestions)->whereHas('paper', fn ($p) => $p->published())->count(),
            'students' => User::where('role', User::ROLE_STUDENT)->count(),
            'newStudents' => User::where('role', User::ROLE_STUDENT)->where('created_at', '>=', now()->subDays(7))->count(),
            'packs' => ContentPack::current()->tier(ContentPack::FULL)->count(),
            'legacy' => $user->isAdmin() ? $tagger->untaggedCount() : 0,
            'drafts' => Paper::where('status', Paper::DRAFT)->with(['exam:id,name', 'subject:id,name'])
                ->withCount(['questions as question_count' => fn ($q) => $q->whereRaw('COALESCE(not_question, 0) = 0')])
                ->latest('updated_at')->limit(6)->get(),
            'isAdmin' => $user->isAdmin(),
        ]);
    }
}
