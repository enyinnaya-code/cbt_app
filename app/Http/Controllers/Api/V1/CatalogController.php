<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentPack;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CatalogController extends Controller
{
    /**
     * Exams, their subjects and the current downloadable pack for each (with size, so the app can show it before downloading).
     * Sends an ETag so a phone that already has the latest catalog spends almost no data re-checking.
     */
    public function index(Request $request): JsonResponse|Response
    {
        $packs = ContentPack::current()->get()->keyBy(fn ($p) => $p->exam_id . '-' . $p->subject_id);

        $exams = Exam::where('is_active', true)->orderBy('sort_order')->with(['subjects' => fn ($q) => $q->where('subjects.is_active', true)->orderBy('subjects.name')])->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'slug' => $exam->slug,
                'name' => $exam->name,
                'subjects' => $exam->subjects->map(function ($subject) use ($exam, $packs) {
                    $pack = $packs->get($exam->id . '-' . $subject->id);

                    return [
                        'id' => $subject->id,
                        'slug' => $subject->slug,
                        'name' => $subject->name,
                        'display_name' => $subject->pivot->display_name ?: $subject->name,
                        'code' => $subject->code,
                        'pack' => $pack ? [
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
            'mock' => collect(config('testacbt.mock'))->map(fn ($f) => [
                'label' => $f['label'],
                'subject_count' => $f['subject_count'],
                'compulsory' => $f['compulsory'] ?? null,
                'questions' => $f['questions'],
                'minutes' => $f['minutes'],
                'score_max' => $f['score_max'],
            ])->all(),
            'practice_counts' => config('testacbt.practice_counts'),
            'strong_accuracy' => config('testacbt.strong_accuracy'),
        ];
        $etag = '"' . md5(json_encode($body)) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304)->header('ETag', $etag);
        }

        return response()->json($body)->header('ETag', $etag);
    }
}
