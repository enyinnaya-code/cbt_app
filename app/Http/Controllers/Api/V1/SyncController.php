<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ProgressRecorder;
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

        $recorder = new ProgressRecorder($request->user());
        $now = now();

        $result = DB::transaction(function () use ($recorder, $data, $now) {
            return [
                'attempts' => $recorder->attempts($data['attempts'] ?? [], $now),
                'bookmarks' => $recorder->bookmarks($data['bookmarks'] ?? [], $now),
                'mock_sessions' => $recorder->mockSessions($data['mock_sessions'] ?? [], $now),
            ];
        });

        return response()->json([
            'accepted' => ['attempts' => $result['attempts']['accepted'], 'bookmarks' => $result['bookmarks']['accepted'], 'mock_sessions' => $result['mock_sessions']],
            'rejected' => ['attempts' => $result['attempts']['rejected'], 'bookmarks' => $result['bookmarks']['rejected']],
            'server_time' => $now->toIso8601String(),
        ]);
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

    /** Stored times have no offset; hand the app full ISO-8601 so it never has to guess the timezone. */
    private function iso(?string $value): ?string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : null;
    }
}
