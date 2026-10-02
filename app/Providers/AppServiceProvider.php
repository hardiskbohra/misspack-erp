<?php

namespace App\Providers;

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
        View::share('assetVer', function (string $path): string {
            $url = asset($path);
            $stamp = @filemtime(public_path($path));

            return $stamp ? $url.'?v='.$stamp : $url;
        });
    }
}
