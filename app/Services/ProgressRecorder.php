<?php

namespace App\Services;

use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The one place that stores a student's answers, bookmarks and mock results.
 * The mobile API and the website both use it, so they follow exactly the same rules:
 * the answer key decides correctness, retries never double count, and bad clocks cannot write future dates.
 */
class ProgressRecorder
{
    public function __construct(private User $user) {}

    /**
     * @param  array<int, array{client_uuid:string,question_id:int,mode:string,selected?:?string,time_ms?:?int,answered_at:string}>  $items
     * @return array{accepted:int, rejected:int}
     */
    public function attempts(array $items, ?Carbon $now = null): array
    {
        $now ??= now();
        $questions = Question::whereIn('id', collect($items)->pluck('question_id')->unique())->get(['id', 'answer', 'not_question'])->keyBy('id');

        $rows = [];
        $rejected = 0;

        foreach ($items as $a) {
            $q = $questions->get($a['question_id']);
            if (! $q || (int) $q->not_question === 1) { $rejected++; continue; }

            $selected = isset($a['selected']) ? strtoupper($a['selected']) : null;
            $rows[] = [
                'user_id' => $this->user->id,
                'client_uuid' => $a['client_uuid'],
                'question_id' => $q->id,
                'mode' => $a['mode'],
                'selected' => $selected,
                'is_correct' => $selected !== null && $selected === strtoupper(trim((string) $q->answer)),
                'time_ms' => $a['time_ms'] ?? null,
                'answered_at' => self::clampToNow($a['answered_at'], $now),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $accepted = 0;
        foreach (array_chunk($rows, 200) as $chunk) {
            $accepted += DB::table('question_attempts')->insertOrIgnore($chunk);
        }

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    /**
     * Newest change wins, judged by the device's own timestamp. A change made live on the website is
     * $authoritative: the tap is happening now, so it always applies (two taps in one second must both count).
     *
     * @param  array<int, array{question_id:int,bookmarked:bool,changed_at:string}>  $items
     * @return array{accepted:int, rejected:int}
     */
    public function bookmarks(array $items, ?Carbon $now = null, bool $authoritative = false): array
    {
        $now ??= now();
        $known = Question::whereIn('id', collect($items)->pluck('question_id')->unique())->pluck('id')->flip();

        $incoming = collect($items)->filter(fn ($b) => $known->has($b['question_id']));
        $rejected = count($items) - $incoming->count();

        $existing = DB::table('bookmarks')->where('user_id', $this->user->id)
            ->whereIn('question_id', $incoming->pluck('question_id'))->pluck('changed_at', 'question_id');

        $upserts = [];
        foreach ($incoming->sortBy('changed_at')->keyBy('question_id') as $qid => $b) {
            $changedAt = self::clampToNow($b['changed_at'], $now);
            if (! $authoritative && isset($existing[$qid]) && Carbon::parse($existing[$qid])->gte($changedAt)) { continue; }

            $upserts[] = [
                'user_id' => $this->user->id, 'question_id' => $qid, 'is_bookmarked' => (bool) $b['bookmarked'],
                'changed_at' => $changedAt, 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        if ($upserts) {
            DB::table('bookmarks')->upsert($upserts, ['user_id', 'question_id'], ['is_bookmarked', 'changed_at', 'updated_at']);
        }

        return ['accepted' => count($upserts), 'rejected' => $rejected];
    }

    /**
     * @param  array<int, array<string,mixed>>  $items
     */
    public function mockSessions(array $items, ?Carbon $now = null): int
    {
        $now ??= now();

        $rows = array_map(fn ($m) => [
            'user_id' => $this->user->id, 'client_uuid' => $m['client_uuid'], 'exam_id' => $m['exam_id'],
            'subject_scores' => json_encode($m['subject_scores']), 'score' => $m['score'], 'total' => $m['total'],
            'duration_seconds' => $m['duration_seconds'], 'taken_at' => self::clampToNow($m['taken_at'], $now),
            'created_at' => $now, 'updated_at' => $now,
        ], $items);

        return $rows ? DB::table('mock_sessions')->insertOrIgnore($rows) : 0;
    }

    /**
     * Devices send UTC; convert to the app timezone so stored values match created_at and compare correctly.
     * A device with a wrong clock must not be able to write records dated in the future.
     */
    public static function clampToNow(string $value, Carbon $now): Carbon
    {
        $t = Carbon::parse($value)->setTimezone(config('app.timezone'));

        return $t->greaterThan($now->copy()->addDay()) ? $now->copy() : $t;
    }
}
