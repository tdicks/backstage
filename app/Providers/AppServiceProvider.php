<?php

namespace App\Providers;

use App\Models\JamSession;
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
            $view->with('navJamSessions', JamSession::query()
                ->visibleTo(request()->user())
                ->orderByDesc('date')
                ->get(['id', 'name', 'date']));
        });
    }
}
