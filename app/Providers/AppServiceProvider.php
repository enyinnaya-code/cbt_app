<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use App\Models\Announcement;
use Illuminate\Support\Facades\Auth;





class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */


   public function boot()
{
    Paginator::useBootstrapFour();

    View::composer('includes.right_top_nav', function ($view) {
        if (Auth::check()) {
            $user = \App\Models\User::find(Auth::id());

            // Get recent announcements
            $recentAnnouncements = Announcement::latest()->take(5)->get();

            // Ensure each announcement has a relationship with the current user
            foreach ($recentAnnouncements as $announcement) {
                // Check if there's already a record
                $pivotRecord = $user->announcements()
                    ->where('announcement_id', $announcement->id)
                    ->first();

                // If no record exists, create one (this is the first time seeing this announcement)
                if (!$pivotRecord) {
                    $user->announcements()->attach($announcement->id);
                }
            }

            $view->with('recentAnnouncements', $recentAnnouncements);
        } else {
            $view->with('recentAnnouncements', collect([]));
        }
    });
}
}
