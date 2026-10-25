<?php

// Real Blade output with fictional records in a disposable in-memory database.
// APP_ENV=testing php tools/testing/render-dashboard-analytics.php .work/dashboards
if (getenv('APP_ENV') !== 'testing') { fwrite(STDERR, "Requires APP_ENV=testing.\n"); exit(1); }
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
\Tests\Support\AnalyticsDatabaseFixture::install();
\Carbon\Carbon::setTestNow('2026-10-25 12:00:00');
$fixture = \Tests\Support\AnalyticsDatabaseFixture::seed();
app('url')->forceRootUrl('http://analytics.test');
auth()->setUser($fixture['user']);
$output = $argv[1] ?? base_path('.work/dashboards');
if (! is_dir($output)) mkdir($output, 0777, true);
$periods = app(\App\Services\DashboardPeriod::class);
$dashboards = app(\App\Services\ModuleDashboard::class);
foreach (['overview', 'directory', ...array_keys(\App\Services\ModuleDashboard::MODULES)] as $module) {
    $url = $module === 'overview' ? '/dashboard' : ($module === 'directory' ? '/dashboards' : '/dashboards/'.$module);
    $request = \Illuminate\Http\Request::create($url, 'GET', ['period_type'=>'custom','start_date'=>'2026-10-10','end_date'=>'2026-10-12','currency'=>'INR']);
    $request->setUserResolver(fn () => $fixture['user']);
    app()->instance('request', $request);
    app('url')->setRequest($request);
    view()->share('errors', new \Illuminate\Support\ViewErrorBag);
    $view = match ($module) {
        'overview' => app(\App\Http\Controllers\AdminDashboardController::class)->index($request),
        'directory' => app(\App\Http\Controllers\ModuleDashboardController::class)->index(),
        default => app(\App\Http\Controllers\ModuleDashboardController::class)->show($request, $module, $periods, $dashboards),
    };
    file_put_contents($output.'/'.$module.'.html', $view->render());
}
echo "Rendered executive overview, directory and six real module dashboard views.\n";
