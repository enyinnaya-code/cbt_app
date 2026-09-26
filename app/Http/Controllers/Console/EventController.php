<?php

namespace App\Http\Controllers\Console;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Upcoming exam dates, deadlines and webinars shown on the landing page. Admins only (see routes). */
class EventController extends Controller
{
    public function index()
    {
        return view('console.events.index', [
            'upcoming' => Event::upcoming()->with('exam:id,name')->orderBy('starts_on')->get(),
            'past' => Event::where('starts_on', '<', today())->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '<', today()))
                ->with('exam:id,name')->orderByDesc('starts_on')->limit(15)->get(),
        ]);
    }

    public function create()
    {
        return view('console.events.form', ['event' => new Event(['kind' => 'exam', 'status' => 'published', 'starts_on' => today()->addWeek()]), 'exams' => $this->exams()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $event = Event::create($data + ['slug' => Event::uniqueSlug($data['title'])]);

        return redirect()->route('console.events.index')->with('success', "Added \"{$event->title}\".");
    }

    public function edit(Event $event)
    {
        return view('console.events.form', ['event' => $event, 'exams' => $this->exams()]);
    }

    public function update(Request $request, Event $event)
    {
        $event->update($this->validated($request));

        return redirect()->route('console.events.index')->with('success', 'Saved.');
    }

    public function destroy(Event $event)
    {
        $event->delete();

        return redirect()->route('console.events.index')->with('success', 'Deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'kind' => ['required', Rule::in(array_keys(Event::kinds()))],
            'description' => ['nullable', 'string', 'max:500'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'location' => ['nullable', 'string', 'max:160'],
            'link_url' => ['nullable', 'url:http,https', 'max:500'],
            'exam_id' => ['nullable', 'exists:exams,id'],
            'status' => ['required', 'in:draft,published'],
        ], [
            'ends_on.after_or_equal' => 'The end date cannot be before the start date.',
            'link_url.url' => 'The link must start with http:// or https://.',
        ]);

        foreach (['description', 'ends_on', 'location', 'link_url', 'exam_id'] as $optional) {
            $data[$optional] = filled($data[$optional] ?? null) ? $data[$optional] : null;
        }

        return $data;
    }

    private function exams()
    {
        return Exam::orderBy('sort_order')->get(['id', 'name']);
    }
}
