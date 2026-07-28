<?php

namespace App\Providers;

use App\Models\JamSession;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\NotificationTypeCatalog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
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
        Event::listen(Registered::class, function (Registered $event): void {
            app(NotificationService::class)->notifyUsers(
                NotificationTypeCatalog::ADMIN_USER_REGISTERED,
                User::query()->where('is_admin', true)->get(),
                $event->user,
                [
                    'title' => 'New user registered',
                    'body' => $event->user->name.' registered for Backstage.',
                    'action_url' => route('admin.users.index'),
                    'action_label' => 'Manage users',
                ]
            );
        });

        View::composer('layouts.navigation', function ($view): void {
            $user = request()->user();
            $notificationFeed = $user
                ? app(NotificationService::class)->feedForUser($user, 15)
                : ['notifications' => [], 'unread_count' => 0];

            $view->with('navJamSessions', JamSession::query()
                ->visibleTo(request()->user())
                ->where('is_archived', false)
                ->orderByDesc('date')
                ->get(['id', 'name', 'date', 'is_closed', 'is_hidden', 'allow_checkins', 'is_live']));

            $view->with('hasArchivedJamSessions', JamSession::query()
                ->visibleTo($user)
                ->where('is_archived', true)
                ->exists());
            $view->with('navNotificationFeed', $notificationFeed);
        });
    }
}
