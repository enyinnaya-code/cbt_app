<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\Access;
use App\Services\Pricing;
use App\Services\QuestionSelector;
use Illuminate\Http\Request;

class PricingController extends Controller
{
    /** What is free, what costs money and how much. Open to everyone; signed-in students also see what they already own. */
    public function index(Request $request, QuestionSelector $selector)
    {
        $exams = Exam::where('is_active', true)->orderBy('sort_order')->get();
        $exam = $exams->firstWhere('slug', $request->query('exam')) ?? $exams->first();

        $access = $request->user() ? Access::for($request->user()) : null;
        $available = $exam ? $selector->availability($exam) : collect();

        $rows = $exam
            ? $exam->subjects()->where('subjects.is_active', true)->orderBy('subjects.name')->get()
                ->map(fn ($s) => (object) [
                    'name' => $s->pivot->display_name ?: $s->name,
                    'code' => $s->code,
                    'slug' => $s->slug,
                    'total' => (int) ($available[$s->id] ?? 0),
                    'price' => Pricing::price($s->pivot),
                    'free' => Pricing::freeQuestions($s->pivot),
                    'expires' => $access?->expiresAt($exam->id, $s->id),
                ])
                ->filter(fn ($r) => $r->total > 0)->values()
            : collect();

        $paid = $rows->where('price', '>', 0);

        return view('site.pricing', [
            'exams' => $exams,
            'exam' => $exam,
            'rows' => $rows,
            'bundle' => $exam?->bundle_price,
            'bundleSaves' => $exam?->bundle_price ? max(0, $paid->sum('price') - $exam->bundle_price) : 0,
            'days' => Pricing::accessDays(),
        ]);
    }
}
