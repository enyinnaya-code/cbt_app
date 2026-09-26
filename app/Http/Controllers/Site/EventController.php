<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Event;

class EventController extends Controller
{
    public function index()
    {
        return view('site.events', [
            'upcoming' => Event::live()->upcoming()->with('exam:id,name')->orderBy('starts_on')->get(),
            'past' => Event::live()->where('starts_on', '<', today())->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '<', today()))
                ->orderByDesc('starts_on')->limit(8)->get(),
        ]);
    }
}
