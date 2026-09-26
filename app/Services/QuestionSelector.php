<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Support\HtmlCleaner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Chooses and prepares questions for Practice, Saved and Mock sessions.
 * Only questions from published papers with a usable answer key are ever offered.
 *
 * Every method returns ['questions' => [...], 'passages' => [id => html]]. A passage is a reading text or
 * instruction row that sits in front of a few questions; it is attached to them by passage_id.
 */
class QuestionSelector
{
    /** An instruction followed by more questions than this is treated as general directions, not a passage. */
    public const PASSAGE_MAX_QUESTIONS = 10;

    /** Exam order is kept when one year is chosen; with "all years" whole passage groups are shuffled together. */
    public function practice(?Exam $exam, Subject $subject, ?int $year, int $count, ?int $topicId = null, ?int $seed = null, ?Access $access = null): array
    {
        // $exam is null when practising a topic, which spans every exam that covers the subject.
        $candidates = $this->candidates(fn ($paper) => $paper->where('subject_id', $subject->id)
            ->when($exam, fn ($q) => $q->where('exam_id', $exam->id))
            ->when($year, fn ($q) => $q->where('year', $year)), $topicId, null, $access);

        return $this->pick($candidates, $count, keepOrder: $year !== null, seed: $seed);
    }

    /**
     * Ids for one subject of a mock exam (all years mixed, passage groups kept together).
     *
     * @return array<int, int>
     */
    public function mockIds(Exam $exam, Subject $subject, int $count, ?int $seed = null): array
    {
        $candidates = $this->candidates(fn ($paper) => $paper->where('exam_id', $exam->id)->where('subject_id', $subject->id));

        return $this->choose($candidates, $count, keepOrder: false, seed: $seed)->pluck('id')->all();
    }

    /**
     * Rebuilds a stored question list in exactly the order given. Used to show a mock exam again after a refresh.
     * Without $withKey the answer and explanations are left out, so the browser cannot peek during the exam.
     */
    public function byIds(array $ids, bool $withKey): array
    {
        if (! $ids) { return ['questions' => [], 'passages' => []]; }

        $questions = Question::whereIn('id', $ids)->with(['topic:id,name', 'paper:id,year'])->get()->keyBy('id');
        $map = $this->passageMap($questions->pluck('paper_id')->unique()->all());

        $ordered = collect($ids)->map(fn ($id) => $questions->get($id))->filter()
            ->each(fn (Question $q) => $q->setAttribute('passage_id', $map[$q->id] ?? null))->values();

        return $this->payload($ordered, $withKey);
    }

    public function saved(User $user, int $count, ?int $seed = null, ?Access $access = null): array
    {
        $ids = DB::table('bookmarks')->where('user_id', $user->id)->where('is_bookmarked', true)->pluck('question_id');

        $candidates = $this->candidates(fn ($paper) => $paper, null, $ids->all(), $access);

        return $this->pick($candidates, $count, keepOrder: false, seed: $seed);
    }

    /**
     * The free sample of a subject: the first $limit usable questions, newest year first, in paper order.
     * Reads in small batches and stops as soon as it has enough, so it stays cheap on big subjects.
     *
     * @return array<int,int> question ids
     */
    public function freeIds(int $examId, int $subjectId, int $limit): array
    {
        $ids = [];
        $offset = 0;
        $batch = max(100, $limit * 2);

        while ($limit > 0 && count($ids) < $limit) {
            $rows = Question::query()
                ->join('papers', 'papers.id', '=', 'questions.paper_id')
                ->where('papers.status', 'published')->where('papers.exam_id', $examId)->where('papers.subject_id', $subjectId)
                ->whereRaw('COALESCE(questions.not_question, 0) = 0')
                ->orderByDesc('papers.year')->orderBy('papers.id')->orderBy('questions.id')
                ->select('questions.id', 'questions.answer', 'questions.options')
                ->offset($offset)->limit($batch)->get();

            if ($rows->isEmpty()) { break; }

            foreach ($rows as $row) {
                if ($this->usable($row)) {
                    $ids[] = (int) $row->id;
                    if (count($ids) >= $limit) { break; }
                }
            }

            $offset += $batch;
        }

        return $ids;
    }

    /** How many usable questions each subject has for an exam, so the setup screen can show what is available. */
    public function availability(Exam $exam): Collection
    {
        return Question::query()
            ->join('papers', 'papers.id', '=', 'questions.paper_id')
            ->where('papers.status', 'published')->where('papers.exam_id', $exam->id)
            ->whereRaw('COALESCE(questions.not_question, 0) = 0')
            ->whereNotNull('questions.answer')->where('questions.answer', '!=', '')
            ->groupBy('papers.subject_id')
            ->select('papers.subject_id', DB::raw('COUNT(*) as questions'))
            ->pluck('questions', 'subject_id');
    }

    /** @return Collection<int,int> years that have a published paper for this exam and subject, newest first */
    public function years(Exam $exam, Subject $subject): Collection
    {
        return \App\Models\Paper::published()->where('exam_id', $exam->id)->where('subject_id', $subject->id)
            ->whereHas('questions')->orderByDesc('year')->pluck('year')->unique()->values();
    }

    // ---------------------------------------------------------------------------------------------

    /** @return Collection<int, Question> usable multiple-choice questions in exam order, each with ->passage_id set */
    private function candidates(callable $paperScope, ?int $topicId = null, ?array $onlyIds = null, ?Access $access = null): Collection
    {
        $questions = Question::query()
            ->whereHas('paper', fn ($p) => $paperScope($p->published()))
            ->whereRaw('COALESCE(questions.not_question, 0) = 0')
            ->when($topicId, fn ($q) => $q->where('topic_id', $topicId))
            ->when($onlyIds !== null, fn ($q) => $q->whereIn('id', $onlyIds ?: [0]))
            ->with(['topic:id,name', 'paper:id,year,exam_id,subject_id'])
            ->orderBy('paper_id')->orderBy('id')
            ->get()
            ->filter(fn (Question $q) => $this->usable($q) && ($access === null || $access->allowsQuestion($q)))
            ->values();

        $map = $this->passageMap($questions->pluck('paper_id')->unique()->all());

        return $questions->each(fn (Question $q) => $q->setAttribute('passage_id', $map[$q->id] ?? null));
    }

    private function usable(Question $q): bool
    {
        $options = $this->options($q);
        $answer = strtoupper(trim((string) $q->answer));

        return $answer !== '' && isset($options[$answer]) && count($options) >= 2;
    }

    /** @return array<string,string> letter => option html, empty options removed */
    private function options(Question $q): array
    {
        $raw = is_array($q->options) ? $q->options : json_decode((string) $q->options, true);

        return is_array($raw) ? array_filter($raw, fn ($o) => $o !== null && $o !== '') : [];
    }

    /**
     * For each multiple-choice question, the instruction row that introduces it (or null for general directions).
     *
     * @return array<int, int|null> question id => passage id
     */
    private function passageMap(array $paperIds): array
    {
        if (! $paperIds) { return []; }

        $rows = Question::whereIn('paper_id', $paperIds)->orderBy('paper_id')->orderBy('id')->get(['id', 'paper_id', 'not_question']);

        $owner = [];        // question id => instruction id
        $groupSize = [];    // instruction id => number of questions after it
        $current = null;
        $currentPaper = null;

        foreach ($rows as $row) {
            if ($row->paper_id !== $currentPaper) { $current = null; $currentPaper = $row->paper_id; }

            if ((int) $row->not_question === 1) { $current = $row->id; continue; }
            if ($current !== null) {
                $owner[$row->id] = $current;
                $groupSize[$current] = ($groupSize[$current] ?? 0) + 1;
            }
        }

        return array_map(fn ($instructionId) => $groupSize[$instructionId] <= self::PASSAGE_MAX_QUESTIONS ? $instructionId : null, $owner);
    }

    private function pick(Collection $candidates, int $count, bool $keepOrder, ?int $seed): array
    {
        return $this->payload($this->choose($candidates, $count, $keepOrder, $seed));
    }

    private function choose(Collection $candidates, int $count, bool $keepOrder, ?int $seed): Collection
    {
        if (! $keepOrder) {
            // Shuffle whole passage groups (a lone question is its own group) so a passage never gets split up.
            $candidates = $candidates
                ->groupBy(fn (Question $q) => $q->passage_id ? 'p' . $q->passage_id : 'q' . $q->id)
                ->shuffle($seed)
                ->flatten(1);
        }

        return $candidates->take(max(1, $count))->values();
    }

    private function payload(Collection $questions, bool $withKey = true): array
    {
        $passageIds = $questions->pluck('passage_id')->filter()->unique()->all();
        $passages = $passageIds
            ? Question::whereIn('id', $passageIds)->pluck('question', 'id')->map(fn ($html) => HtmlCleaner::clean($html))->all()
            : [];

        return [
            'questions' => $questions->map(function (Question $q) use ($withKey) {
                $item = [
                    'id' => $q->id,
                    'html' => HtmlCleaner::clean($q->question),
                    'options' => array_map(fn ($o) => HtmlCleaner::clean((string) $o), $this->options($q)),
                    'marks' => (int) ($q->mark ?: 1),
                    'topic' => $q->topic?->name,
                    'year' => $q->paper?->year,
                    'passage_id' => $q->passage_id,
                ];

                if ($withKey) {
                    $item['answer'] = strtoupper(trim((string) $q->answer));
                    $item['explanation_en'] = $q->explanation_en ? HtmlCleaner::clean($q->explanation_en) : null;
                    $item['explanation_pcm'] = $q->explanation_pcm ? HtmlCleaner::clean($q->explanation_pcm) : null;
                }

                return $item;
            })->all(),
            'passages' => $passages,
        ];
    }
}
