<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;


class AnnouncementController extends Controller
{
    //

    public function index(Request $request)
    {
        $query = Announcement::with('user')->latest();

        if ($request->filled('filter_text')) {
            $query->where('content', 'like', '%' . $request->filter_text . '%');
        }

        if ($request->filled('filter_date_from')) {
            $query->whereDate('created_at', '>=', $request->filter_date_from);
        }

        if ($request->filled('filter_date_to')) {
            $query->whereDate('created_at', '<=', $request->filter_date_to);
        }

        $announcements = $query->paginate(15)->withQueryString();

        return view('all_announcements', compact('announcements'));
    }



    public function create()
    {
        return view('send_announcement');
    }

    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required|string',
        ]);

        Announcement::create([
            'content' => $request->content,
            'user_id' => Auth::id(),
        ]);


        return redirect()->route('announcements.index')->with('success', 'Announcement sent successfully.');
    }


    public function edit(Announcement $announcement)
    {
        return view('edit_announcement', compact('announcement'));
    }

    public function update(Request $request, Announcement $announcement)
    {
        $request->validate([
            'content' => 'required|string|max:500',
        ]);

        $announcement->update(['content' => $request->content]);

        return redirect()->route('announcements.index')->with('success', 'Announcement updated successfully.');
    }

    public function destroy(Announcement $announcement)
    {
        $announcement->delete();

        return redirect()->route('announcements.index')->with('success', 'Announcement deleted successfully.');
    }

    public function markAllRead()
    {
        $user = \App\Models\User::find(Auth::id());
        $user->markAllAnnouncementsAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All announcements marked as read'
        ]);
    }

    /**
     * Mark a specific announcement as read
     */
    public function markAsRead($id)
    {
        $user = \App\Models\User::find(Auth::id());
        $user->markAnnouncementAsRead($id);

        return response()->json([
            'success' => true,
            'message' => 'Announcement marked as read'
        ]);
    }

    public function markAllAsRead()
    {
        $user = \App\Models\User::find(Auth::id());

        // Get all unread announcements for this user
        $unreadAnnouncements = $user->announcements()
            ->wherePivotNull('read_at')
            ->get();

        // Mark all as read
        foreach ($unreadAnnouncements as $announcement) {
            $user->markAnnouncementAsRead($announcement->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'All announcements marked as read'
        ]);
    }


    public function dashboard()
    {
        $user = \App\Models\User::find(Auth::id());

        // Get recent announcements and track read status for the current user
        $recentAnnouncements = Announcement::latest()
            ->take(5)
            ->get()
            ->map(function ($announcement) use ($user) {
                // Ensure the announcement is associated with the current user
                $pivotRecord = $user->announcements()
                    ->where('announcement_id', $announcement->id)
                    ->first();

                // If no record exists, create one (first time user sees this announcement)
                if (!$pivotRecord) {
                    $user->announcements()->attach($announcement->id);
                }

                return $announcement;
            });

        return view('dashboard', compact('recentAnnouncements'));
    }
}
