<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\Video;
use App\Services\VideoLink;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/** Videos shown on the public website. Admins only (see routes). */
class VideoController extends Controller
{
    public function index()
    {
        return view('console.videos.index', ['videos' => Video::with('exam:id,name')->latest('id')->paginate(20)]);
    }

    public function create()
    {
        return view('console.videos.form', ['video' => new Video(['status' => 'published']), 'exams' => $this->exams()]);
    }

    public function store(Request $request)
    {
        $video = Video::create($this->attributes($request) + ['added_by' => $request->user()->id]);

        return redirect()->route('console.videos.index')->with('success', "Added \"{$video->title}\".");
    }

    public function edit(Video $video)
    {
        return view('console.videos.form', ['video' => $video, 'exams' => $this->exams()]);
    }

    public function update(Request $request, Video $video)
    {
        $video->update($this->attributes($request));

        return redirect()->route('console.videos.index')->with('success', 'Saved.');
    }

    public function destroy(Video $video)
    {
        $video->delete();

        return redirect()->route('console.videos.index')->with('success', 'Deleted.');
    }

    /** The pasted link is checked and turned into the player address here; nothing typed is ever used as a player. */
    private function attributes(Request $request): array
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:400'],
            'exam_id' => ['nullable', 'exists:exams,id'],
            'status' => ['required', 'in:draft,published'],
            'is_featured' => ['nullable', 'boolean'],
        ]);

        try {
            $link = VideoLink::parse($data['url']);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages(['url' => $e->getMessage()]);
        }

        return [
            'title' => trim($data['title']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'platform' => $link['platform'],
            'external_id' => $link['external_id'],
            'url' => $link['url'],
            'embed_url' => $link['embed_url'],
            'thumbnail_url' => $link['thumbnail_url'],
            'exam_id' => $data['exam_id'] ?? null,
            'status' => $data['status'],
            'is_featured' => (bool) ($data['is_featured'] ?? false),
        ];
    }

    private function exams()
    {
        return Exam::orderBy('sort_order')->get(['id', 'name']);
    }
}
