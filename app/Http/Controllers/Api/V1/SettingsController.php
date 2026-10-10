<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The study settings a student chooses (explanation language, the exam they are preparing for and its date). They are the
 * same ones the website's Profile page edits, so changing them on either device changes them on both.
 */
class SettingsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'lang' => ['sometimes', Rule::in(['en', 'pcm'])],
            'target_exam' => ['sometimes', 'nullable', Rule::exists('exams', 'slug')],
            'target_exam_date' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:2000-01-01', 'before:2100-01-01'],
        ]);

        $update = [];
        if (array_key_exists('lang', $data)) { $update['explanation_language'] = $data['lang']; }
        if (array_key_exists('target_exam', $data)) { $update['target_exam'] = $data['target_exam']; }
        if (array_key_exists('target_exam_date', $data)) { $update['target_exam_date'] = $data['target_exam_date']; }

        $request->user()->update($update);

        return response()->json($this->payload($request));
    }

    private function payload(Request $request): array
    {
        $user = $request->user()->fresh();

        return [
            'lang' => $user->explanation_language ?: 'en',
            'target_exam' => $user->target_exam,
            'target_exam_date' => $user->target_exam_date?->toDateString(),
        ];
    }
}
