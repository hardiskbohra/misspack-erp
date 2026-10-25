<?php

namespace App\Providers;

use App\Listeners\OfficeBriefingSubscriber;
use App\Models\Organisation;
use App\Services\OfficeBriefing;
use App\Services\SettingsDirectory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
         | What "a good password" means, said in exactly one place. Every form
         | that *sets* a password — the account page's own change, the office's
         | user editor — uses `Password::defaults()`, so the rule cannot drift
         | the way the old min:4 / min:6 / min:8 trio did. Deliberately *not*
         | uncompromised(): that check phones a breach-list service the office
         | network may not reach; mixed case and a number is the floor we can
         | always keep.
         */
        Password::defaults(
            fn (): Password => Password::min(12)->letters()->mixedCase()->numbers()
        );

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

        /* The settings rail. Six screens wear it and none of them should have to
           remember to hand it the list of areas: the composer reads the one
           directory (`App\Services\SettingsDirectory`) every time, so a screen
           added later gets the same rail as the rest, and an area can never be
           missing from the menu of one page and present on another. */
        View::composer('settings.*', function ($view) {
            $view->with('areas', app(SettingsDirectory::class)->areas());
        });

        View::composer('layouts.app', function ($view) {
            $user = Auth::user();
            $empty = ['unread' => 0, 'critical' => 0, 'items' => [], 'toasts' => [], 'popup' => null];

            try {
                $view->with('officeBrand', Organisation::current()->brand());
            } catch (\Throwable $e) {
                $view->with('officeBrand', config('brand'));
            }

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
