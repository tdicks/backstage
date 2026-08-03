<?php

namespace App\Providers;

use App\Http\Controllers\MySetsController;
use App\Models\JamSession;
use App\Models\User;
use App\Services\AppNoticeService;
use App\Services\ManualSlotTransferService;
use App\Services\NotificationService;
use App\Support\NotificationTypeCatalog;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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

            app(ManualSlotTransferService::class)->primeMatchesForNewUser($event->user);
        });

        View::composer('layouts.navigation', function ($view): void {
            $user = request()->user();
            $notificationFeed = $user
                ? app(NotificationService::class)->feedForUser($user, 15)
                : ['notifications' => [], 'unread_count' => 0];
            $pendingApprovalCount = $this->resolvePendingApprovalCount($user);

            $view->with('navJamSessions', JamSession::query()
                ->visibleTo(request()->user())
                ->where('is_archived', false)
                ->orderByDesc('date')
                ->get(['id', 'name', 'date', 'is_closed', 'is_hidden', 'allow_checkins', 'is_live']));

            $view->with('hasArchivedJamSessions', JamSession::query()
                ->visibleTo($user)
                ->where('is_archived', true)
                ->exists());
            $view->with('mySetsApprovalCount', $pendingApprovalCount);
            $view->with('navNotificationFeed', $notificationFeed);
        });

        View::composer(['layouts.app', 'layouts.guest'], function ($view): void {
            $user = request()->user();
            $pageName = $this->resolvePageName();
            $unreadCount = $this->resolveUnreadNotificationCount($user);
            $pendingApprovalCount = $this->resolvePendingApprovalCount($user);
            $pendingTotal = $unreadCount + $pendingApprovalCount;
            $titlePrefix = $pendingTotal > 0 ? "({$pendingTotal}) " : '';

            $view->with('pageName', $pageName);
            $view->with('unreadNotificationCount', $unreadCount);
            $view->with('pendingApprovalCount', $pendingApprovalCount);
            $view->with('pendingTitleCount', $pendingTotal);
            $view->with('documentTitle', $titlePrefix.$pageName.' | Backstage');
        });

        View::composer('layouts.app', function ($view): void {
            $view->with('appNoticesByLocation', app(AppNoticeService::class)->forRequest(request(), request()->user()));
            $view->with('noticeDismissUrlTemplate', route('notices.dismiss', ['notice' => '__NOTICE_ID__']));
        });
    }

    private function resolveUnreadNotificationCount(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        if (request()->attributes->has('backstage.unread_notification_count')) {
            return (int) request()->attributes->get('backstage.unread_notification_count');
        }

        $count = $user->notifications()
            ->whereNull('dismissed_at')
            ->whereNull('read_at')
            ->count();

        request()->attributes->set('backstage.unread_notification_count', $count);

        return $count;
    }

    private function resolvePendingApprovalCount(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        if (request()->attributes->has('backstage.pending_approval_count')) {
            return (int) request()->attributes->get('backstage.pending_approval_count');
        }

        $count = MySetsController::pendingApprovalCount($user);
        request()->attributes->set('backstage.pending_approval_count', $count);

        return $count;
    }

    private function resolvePageName(): string
    {
        $routeName = request()->route()?->getName();

        if (! $routeName) {
            return 'Backstage';
        }

        $segments = explode('.', $routeName);
        $terminalActions = ['index', 'show', 'create', 'edit', 'store', 'update', 'destroy'];

        if (count($segments) > 1 && in_array(end($segments), $terminalActions, true)) {
            array_pop($segments);
        }

        $labels = [
            'dashboard' => 'Dashboard',
            'sessions' => 'Jam Sessions',
            'live' => 'Live',
            'my-sets' => 'My Sets',
            'my_sets' => 'My Sets',
            'directory' => "Who's Who",
            'notifications' => 'Notifications',
            'profile' => 'Profile',
            'admin' => 'Admin',
            'users' => 'Users',
            'attachments' => 'Attachments',
            'settings' => 'Settings',
            'slot-conflicts' => 'Slot Conflicts',
            'slot_conflicts' => 'Slot Conflicts',
            'band-templates' => 'Band Templates',
            'band_templates' => 'Band Templates',
            'help' => 'Help',
            'about' => 'About',
            'privacy' => 'Privacy Policy',
            'verification' => 'Email Verification',
            'password' => 'Password',
            'confirm' => 'Confirm',
            'register' => 'Register',
            'login' => 'Login',
            'share' => 'Shared Set',
        ];

        $parts = collect($segments)
            ->map(fn (string $segment) => $labels[$segment] ?? Str::of($segment)->replace(['-', '_'], ' ')->title()->toString())
            ->filter()
            ->values();

        if ($parts->isEmpty()) {
            return 'Backstage';
        }

        return $parts->join(' ');
    }
}
