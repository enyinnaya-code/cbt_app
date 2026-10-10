<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The student's revision library: sets of questions worth seeing again, all worked out from their own answers,
 * plus a simple 7-day plan built from what they are weakest at.
 */
class Revision
{
    public const SOURCES = [
        'wrong' => ['title' => 'Questions I got wrong', 'text' => 'The last time you answered them, you missed them.'],
        'missed' => ['title' => 'Frequently missed', 'text' => 'Questions you have got wrong two or more times.'],
        'recent' => ['title' => 'Recently attempted', 'text' => 'Everything you answered in the last 7 days.'],
        'saved' => ['title' => 'Saved questions', 'text' => 'Questions you bookmarked.'],
    ];

    public function __construct(private User $user, private QuestionSelector $selector) {}

    /** @return array<int,int> question ids, newest first, at most 300 */
    public function ids(string $source): array
    {
        $uid = $this->user->id;

        return match ($source) {
            'wrong' => DB::table('question_attempts as a')->where('a.user_id', $uid)->where('a.is_correct', 0)
                ->whereRaw('a.id = (SELECT MAX(b.id) FROM question_attempts b WHERE b.user_id = a.user_id AND b.question_id = a.question_id)')
                ->orderByDesc('a.id')->limit(300)->pluck('a.question_id')->all(),
            'missed' => DB::table('question_attempts')->where('user_id', $uid)->where('is_correct', 0)
                ->groupBy('question_id')->havingRaw('COUNT(*) >= 2')->orderByRaw('COUNT(*) DESC')->limit(300)->pluck('question_id')->all(),
            'recent' => DB::table('question_attempts')->where('user_id', $uid)->where('answered_at', '>=', now()->subDays(7))
                ->groupBy('question_id')->orderByRaw('MAX(id) DESC')->limit(300)->pluck('question_id')->all(),
            'saved' => DB::table('bookmarks')->where('user_id', $uid)->where('is_bookmarked', 1)->orderByDesc('changed_at')->limit(300)->pluck('question_id')->all(),
            default => [],
        };
    }

    /** Questions in each set the student can actually open now (locked subjects and unusable rows are left out). @return array<string,int> */
    public function counts(): array
    {
        $access = Access::for($this->user);
        $out = [];
        foreach (array_keys(self::SOURCES) as $source) {
            $ids = $this->ids($source);
            $out[$source] = $ids ? count($this->selector->fromIds($ids, 1000, null, $access)['questions']) : 0;
        }

        return $out;
    }

    /**
     * Seven days, starting today: one day per weak subject, a mixed day, a weak-areas day and a mock exam.
     *
     * @return array<int, array{day:int,title:string,text:string,url:string,today:bool}>
     */
    public function plan(StudentStats $stats): array
    {
        $exams = Exam::where('is_active', true)->orderBy('sort_order')->get();
        $exam = $exams->firstWhere('slug', $this->user->target_exam)
            ?? $exams->firstWhere('slug', ($this->user->preferred_exams ?? [])[0] ?? null)
            ?? $exams->first();
        if (! $exam) {
            return [];
        }

        $available = $this->selector->availability($exam);
        $accuracy = $stats->subjects()->keyBy('subject_id');

        // Subjects with questions, weakest first; subjects not tried yet come after the ones scoring under the strong line.
        $subjects = $exam->subjects()->where('subjects.is_active', true)->get()
            ->filter(fn ($s) => ($available[$s->id] ?? 0) > 0)
            ->sortBy(fn ($s) => isset($accuracy[$s->id]) ? $accuracy[$s->id]->accuracy : 60)
            ->values();
        if ($subjects->isEmpty()) {
            return [];
        }

        $practice = fn ($s = null) => route('practice.index', array_filter(['exam' => $exam->slug, 'subject' => $s?->slug]));
        $weakTopic = $stats->weakTopics(1)->first();

        $days = [];
        foreach ($subjects->take(4) as $s) {
            $a = $accuracy[$s->id] ?? null;
            $days[] = [
                'title' => $s->pivot->display_name ?: $s->name,
                'text' => $a ? "Your accuracy is {$a->accuracy}%. Practise 20 questions." : 'Not tried yet. Start with 20 questions.',
                'url' => $practice($s),
            ];
        }
        while (count($days) < 4) {
            $days[] = ['title' => 'Mixed practice', 'text' => 'Pick any subject and answer 20 questions.', 'url' => $practice()];
        }
        $days[] = ['title' => 'Mixed practice', 'text' => 'Switch between subjects the way the real paper does.', 'url' => $practice()];
        $days[] = $weakTopic
            ? ['title' => 'Weak areas: ' . $weakTopic->topic, 'text' => "{$weakTopic->subject}, {$weakTopic->accuracy}% so far.", 'url' => route('practice.session', ['subject' => $weakTopic->subject_slug, 'topic' => $weakTopic->topic_id, 'mode' => 'instant', 'count' => 20])]
            : ['title' => 'Weak areas', 'text' => 'Go over the questions you got wrong.', 'url' => route('revision')];
        $days[] = ['title' => 'Mock exam', 'text' => 'A full timed paper to see where you stand.', 'url' => route('mock.index')];

        return collect($days)->map(fn ($d, $i) => $d + ['day' => $i + 1, 'today' => $i === 0])->all();
    }
}
