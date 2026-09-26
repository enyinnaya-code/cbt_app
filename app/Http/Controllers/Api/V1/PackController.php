<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ContentPack;
use App\Models\Exam;
use App\Models\Subject;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PackController extends Controller
{
    /**
     * Downloads the pack this student is allowed: the full one when the subject is unlocked, otherwise the small
     * free-sample one. It is served as a file, so HTTP Range requests work and a phone on a weak connection can
     * resume an interrupted download instead of starting again.
     */
    public function show(Request $request, string $exam, string $subject): BinaryFileResponse
    {
        $exam = Exam::where('slug', $exam)->firstOrFail();
        $subject = Subject::where('slug', $subject)->firstOrFail();

        $tier = Access::for($request->user())->full($exam->id, $subject->id) ? ContentPack::FULL : ContentPack::FREE;

        $pack = ContentPack::current()->tier($tier)->where('exam_id', $exam->id)->where('subject_id', $subject->id)->firstOrFail();

        $disk = Storage::disk('local');
        abort_unless($disk->exists($pack->path), 404, 'Pack file is missing.');

        return response()->download($disk->path($pack->path), "{$exam->slug}-{$subject->slug}-{$tier}-v{$pack->version}.json.gz", [
            'Content-Type' => 'application/gzip',
            'X-Pack-Version' => $pack->version,
            'X-Pack-Tier' => $tier,
            'X-Pack-Sha256' => $pack->sha256,
            'Cache-Control' => 'private, max-age=0',
        ]);
    }
}
