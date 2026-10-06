<?php

namespace App\Providers;

use App\Listeners\OfficeBriefingSubscriber;
use App\Services\OfficeBriefing;
use Illuminate\Support\Facades\Auth;
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
        /*
         | assetVer('assets/css/shipments.css')
         |   -> https://host/assets/css/shipments.css?v=1730460000
         |
         | Stylesheets and scripts are served straight from /public with no
         | build step, so a normal reload keeps using the cached copy and a
         | change appears "not to work". Appending the file's own timestamp
         | makes every edit a new URL — a plain refresh is enough.
         |
         | Shared with all views so any module can opt in:
         |   <link rel="stylesheet" href="{{ $assetVer('assets/css/x.css') }}">
         */
        Event::subscribe(OfficeBriefingSubscriber::class);

        View::share('assetVer', function (string $path): string {
            $url = asset($path);
            $stamp = @filemtime(public_path($path));

            return $stamp ? $url.'?v='.$stamp : $url;
        });

        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            $empty = ['unread' => 0, 'critical' => 0, 'items' => [], 'toasts' => [], 'popup' => null];

            if (! $user || ! $user->isAdmin()) {
                $view->with('officeBriefing', $empty);

                return;
            }

            try {
                $view->with('officeBriefing', app(OfficeBriefing::class)->payload($user));
            } catch (\Throwable $e) {
                $view->with('officeBriefing', $empty);
            }
        });
    }
}
