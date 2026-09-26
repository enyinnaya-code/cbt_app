<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentPack;
use App\Models\Exam;
use App\Services\Access;
use App\Services\MockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CatalogController extends Controller
{
    /**
     * Exams, their subjects and the pack this student can download for each (with size, so the app can show it before
     * downloading). Each subject says whether it is unlocked ("full") or only the free sample ("free"), what it costs,
     * and when a purchase ends. Sends an ETag so a phone that already has the latest catalog spends almost no data re-checking.
     */
    public function index(Request $request): JsonResponse|Response
    {
        $access = Access::for($request->user());
        $keyOf = fn ($p) => $p->exam_id . '-' . $p->subject_id;

        $current = ContentPack::current()->get();
        $full = $current->where('tier', ContentPack::FULL)->keyBy($keyOf);
        $free = $current->where('tier', ContentPack::FREE)->keyBy($keyOf);

        $exams = Exam::where('is_active', true)->orderBy('sort_order')->with(['subjects' => fn ($q) => $q->where('subjects.is_active', true)->orderBy('subjects.name')])->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'slug' => $exam->slug,
                'name' => $exam->name,
                'bundle_price' => $exam->bundle_price,
                'subjects' => $exam->subjects->map(function ($subject) use ($exam, $access, $full, $free, $keyOf) {
                    $key = $exam->id . '-' . $subject->id;
                    $unlocked = $access->full($exam->id, $subject->id);
                    $pack = $unlocked ? $full->get($key) : $free->get($key);
                    $fullPack = $full->get($key);

                    return [
                        'id' => $subject->id,
                        'slug' => $subject->slug,
                        'name' => $subject->name,
                        'display_name' => $subject->pivot->display_name ?: $subject->name,
                        'code' => $subject->code,
                        'access' => $unlocked ? 'full' : 'free',
                        'price' => $access->price($exam->id, $subject->id),
                        'free_questions' => $access->freeLimit($exam->id, $subject->id),
                        'expires_at' => $access->expiresAt($exam->id, $subject->id)?->toIso8601String(),
                        // What unlocking would give, so the app can say "unlock all 1,200 questions".
                        'full_question_count' => $fullPack?->question_count,
                        'pack' => $pack ? [
                            'tier' => $pack->tier,
                            'version' => $pack->version,
                            'size_bytes' => $pack->size_bytes,
                            'sha256' => $pack->sha256,
                            'paper_count' => $pack->paper_count,
                            'question_count' => $pack->question_count,
                            'years' => $pack->years,
                            'built_at' => $pack->built_at->toIso8601String(),
                            'url' => url("/api/v1/packs/{$exam->slug}/{$subject->slug}"),
                        ] : null,
                    ];
                })->values(),
            ])->values();

        // Mock exam formats travel with the catalog so the app follows the server's settings when they change.
        $body = [
            'exams' => $exams,
            'mock' => Exam::where('is_active', true)->get()->mapWithKeys(fn ($e) => [$e->slug => app(MockService::class)->format($e)])->map(fn ($f) => [
                'label' => $f['label'],
                'subject_count' => $f['subject_count'],
                'compulsory' => $f['compulsory'] ?? null,
                'questions' => $f['questions'],
                'minutes' => $f['minutes'],
                'score_max' => $f['score_max'],
            ])->all(),
            'practice_counts' => config('testacbt.practice_counts'),
            'strong_accuracy' => config('testacbt.strong_accuracy'),
            'urls' => ['pricing' => url('/pricing'), 'checkout' => url('/checkout')],
        ];
        $etag = '"' . md5(json_encode($body)) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response()->json($body)->header('ETag', $etag);
    }
}
