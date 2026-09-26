<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncController extends Controller
{
    private const PAGE = 1000;

    /**
     * Upload what the student did offline. Safe to send again: every record carries a phone-generated
     * uuid (or, for bookmarks, a timestamp), so a retry after a dropped connection never double counts.
     */
    public function push(Request $request): JsonResponse
    {
        $data = $request->validate([
            'attempts' => ['sometimes', 'array', 'max:500'],
            'attempts.*.client_uuid' => ['required', 'uuid'],
            'attempts.*.question_id' => ['required', 'integer'],
            'attempts.*.mode' => ['required', 'in:practice,mock'],
            'attempts.*.selected' => ['nullable', 'string', 'in:A,B,C,D,E,a,b,c,d,e'],
            'attempts.*.time_ms' => ['nullable', 'integer', 'min:0', 'max:3600000'],
            'attempts.*.answered_at' => ['required', 'date'],

            'bookmarks' => ['sometimes', 'array', 'max:500'],
            'bookmarks.*.question_id' => ['required', 'integer'],
            'bookmarks.*.bookmarked' => ['required', 'boolean'],
            'bookmarks.*.changed_at' => ['required', 'date'],

            'mock_sessions' => ['sometimes', 'array', 'max:50'],
            'mock_sessions.*.client_uuid' => ['required', 'uuid'],
            'mock_sessions.*.exam_id' => ['required', 'integer', 'exists:exams,id'],
            'mock_sessions.*.subject_scores' => ['required', 'array', 'max:10'],
            'mock_sessions.*.subject_scores.*.subject_id' => ['required', 'integer'],
            'mock_sessions.*.subject_scores.*.correct' => ['required', 'integer', 'min:0'],
            'mock_sessions.*.subject_scores.*.total' => ['required', 'integer', 'min:0'],
            'mock_sessions.*.score' => ['required', 'integer', 'min:0', 'max:65535'],
            'mock_sessions.*.total' => ['required', 'integer', 'min:0', 'max:65535'],
            'mock_sessions.*.duration_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'mock_sessions.*.taken_at' => ['required', 'date'],
        ]);

        $user = $request->user();
        $now = now();
        $result = ['attempts' => 0, 'bookmarks' => 0, 'mock_sessions' => 0];
        $rejected = ['attempts' => 0, 'bookmarks' => 0];

        // The answer key decides correctness. The phone's claim is never trusted.
        $questionIds = collect($data['attempts'] ?? [])->pluck('question_id')
            ->merge(collect($data['bookmarks'] ?? [])->pluck('question_id'))->unique()->values();
        $questions = Question::whereIn('id', $questionIds)->get(['id', 'answer', 'not_question'])->keyBy('id');

        DB::transaction(function () use ($data, $user, $now, $questions, &$result, &$rejected) {
            $rows = [];
            foreach ($data['attempts'] ?? [] as $a) {
                $q = $questions->get($a['question_id']);
                if (! $q || (int) $q->not_question === 1) { $rejected['attempts']++; continue; }

                $selected = isset($a['selected']) ? strtoupper($a['selected']) : null;
                $rows[] = [
                    'user_id' => $user->id,
                    'client_uuid' => $a['client_uuid'],
                    'question_id' => $q->id,
                    'mode' => $a['mode'],
                    'selected' => $selected,
                    'is_correct' => $selected !== null && $selected === strtoupper(trim((string) $q->answer)),
                    'time_ms' => $a['time_ms'] ?? null,
                    'answered_at' => $this->clampToNow($a['answered_at'], $now),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 200) as $chunk) {
                $result['attempts'] += DB::table('question_attempts')->insertOrIgnore($chunk);
            }

            // Bookmarks: newest change wins, judged by the phone's own timestamps.
            $incoming = collect($data['bookmarks'] ?? [])->filter(fn ($b) => $questions->has($b['question_id']));
            $rejected['bookmarks'] = count($data['bookmarks'] ?? []) - $incoming->count();

            $existing = DB::table('bookmarks')->where('user_id', $user->id)
                ->whereIn('question_id', $incoming->pluck('question_id'))->pluck('changed_at', 'question_id');

            $upserts = [];
            foreach ($incoming->sortBy('changed_at')->keyBy('question_id') as $qid => $b) {
                $changedAt = $this->clampToNow($b['changed_at'], $now);
                if (isset($existing[$qid]) && Carbon::parse($existing[$qid])->gte(Carbon::parse($changedAt))) { continue; }

                $upserts[] = [
                    'user_id' => $user->id, 'question_id' => $qid, 'is_bookmarked' => (bool) $b['bookmarked'],
                    'changed_at' => $changedAt, 'created_at' => $now, 'updated_at' => $now,
                ];
            }
            if ($upserts) {
                DB::table('bookmarks')->upsert($upserts, ['user_id', 'question_id'], ['is_bookmarked', 'changed_at', 'updated_at']);
            }
            $result['bookmarks'] = count($upserts);

            $mocks = [];
            foreach ($data['mock_sessions'] ?? [] as $m) {
                $mocks[] = [
                    'user_id' => $user->id, 'client_uuid' => $m['client_uuid'], 'exam_id' => $m['exam_id'],
                    'subject_scores' => json_encode($m['subject_scores']), 'score' => $m['score'], 'total' => $m['total'],
                    'duration_seconds' => $m['duration_seconds'], 'taken_at' => $this->clampToNow($m['taken_at'], $now),
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            if ($mocks) {
                $result['mock_sessions'] = DB::table('mock_sessions')->insertOrIgnore($mocks);
            }
        });

        return response()->json(['accepted' => $result, 'rejected' => $rejected, 'server_time' => $now->toIso8601String()]);
    }

    /**
     * Restore progress on a new phone, or catch up after using another device. Pass back next_cursor and
     * server_time from the previous call to receive only what is new.
     */
    public function pull(Request $request): JsonResponse
    {
        $v = $request->validate([
            'cursor' => ['nullable', 'integer', 'min:0'],
            'since' => ['nullable', 'date'],
        ]);

        $user = $request->user();
        $cursor = (int) ($v['cursor'] ?? 0);
        $since = isset($v['since']) ? Carbon::parse($v['since'])->setTimezone(config('app.timezone')) : null;
        $now = now();

        $attempts = DB::table('question_attempts')->where('user_id', $user->id)->where('id', '>', $cursor)
            ->orderBy('id')->limit(self::PAGE + 1)
            ->get(['id', 'client_uuid', 'question_id', 'mode', 'selected', 'is_correct', 'time_ms', 'answered_at']);

        $hasMore = $attempts->count() > self::PAGE;
        $attempts = $attempts->take(self::PAGE)->map(fn ($a) => (object) array_merge((array) $a, [
            'is_correct' => (bool) $a->is_correct,
            'answered_at' => $this->iso($a->answered_at),
        ]));

        $bookmarks = DB::table('bookmarks')->where('user_id', $user->id)
            ->when($since, fn ($q) => $q->where('changed_at', '>', $since))
            ->get(['question_id', 'is_bookmarked as bookmarked', 'changed_at'])
            ->map(fn ($b) => (object) array_merge((array) $b, [
                'bookmarked' => (bool) $b->bookmarked,
                'changed_at' => $this->iso($b->changed_at),
            ]));

        $mocks = DB::table('mock_sessions')->where('user_id', $user->id)
            ->when($since, fn ($q) => $q->where('created_at', '>', $since))
            ->orderBy('taken_at')->get(['client_uuid', 'exam_id', 'subject_scores', 'score', 'total', 'duration_seconds', 'taken_at'])
            ->map(fn ($m) => (object) array_merge((array) $m, [
                'subject_scores' => json_decode($m->subject_scores, true),
                'taken_at' => $this->iso($m->taken_at),
            ]));

        return response()->json([
            'attempts' => $attempts->values(),
            'has_more' => $hasMore,
            'next_cursor' => $attempts->isEmpty() ? $cursor : $attempts->last()->id,
            'bookmarks' => $bookmarks,
            'mock_sessions' => $mocks->values(),
            'server_time' => $now->toIso8601String(),
        ]);
    }

    /**
     * A phone with a wrong clock must not be able to write records dated in the future.
     * Phones send UTC; convert to the app timezone so stored values match created_at and compare correctly.
     */
    private function clampToNow(string $value, Carbon $now): Carbon
    {
        $t = Carbon::parse($value)->setTimezone(config('app.timezone'));

        return $t->greaterThan($now->copy()->addDay()) ? $now->copy() : $t;
    }

    /** Stored times have no offset; hand the app full ISO-8601 so it never has to guess the timezone. */
    private function iso(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }
}
