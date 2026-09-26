<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Video;
use App\Services\VideoLink;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $platform = array_key_exists((string) $request->query('platform'), VideoLink::LABELS) ? $request->query('platform') : null;
        $exam = Exam::where('is_active', true)->where('slug', (string) $request->query('exam'))->first();

        return view('site.videos', [
            'videos' => Video::live()->with('exam:id,name')
                ->when($platform, fn ($q) => $q->where('platform', $platform))
                ->when($exam, fn ($q) => $q->where('exam_id', $exam->id))
                ->orderByDesc('is_featured')->orderByDesc('id')->paginate(12)->withQueryString(),
            'platform' => $platform,
            'exam' => $exam,
            'platforms' => Video::live()->distinct()->pluck('platform')->all(),
            'exams' => Exam::where('is_active', true)->whereIn('id', Video::live()->whereNotNull('exam_id')->pluck('exam_id'))->orderBy('sort_order')->get(['id', 'name', 'slug']),
        ]);
    }
}
