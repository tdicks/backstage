<?php

namespace App\Providers;

use App\Models\JamSession;
use App\Services\NotificationService;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
    public function boot(): void
    {
        View::composer('layouts.navigation', function ($view): void {
            $user = request()->user();
            $notificationFeed = $user
                ? app(NotificationService::class)->feedForUser($user, 15)
                : ['notifications' => [], 'unread_count' => 0];

            $view->with('navJamSessions', JamSession::query()
                ->visibleTo(request()->user())
                ->where('is_archived', false)
                ->orderByDesc('date')
                ->get(['id', 'name', 'date']));

            $view->with('hasArchivedJamSessions', JamSession::query()
                ->visibleTo($user)
                ->where('is_archived', true)
                ->exists());
            $view->with('navNotificationFeed', $notificationFeed);
        });
    }
}
