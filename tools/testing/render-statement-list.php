<?php

// APP_ENV=testing php tools/testing/render-statement-list.php .work/statement-list
if (getenv('APP_ENV') !== 'testing') { fwrite(STDERR, "Requires APP_ENV=testing.\n"); exit(1); }
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fixture = \Tests\Support\StatementListDatabaseFixture::install();
auth()->setUser($fixture['user']);
app('url')->forceRootUrl('http://statements.test');
$output = $argv[1] ?? base_path('.work/statement-list');
if (! is_dir($output)) mkdir($output,0777,true);
$variants = ['default'=>[], 'all'=>['period'=>'all'], 'usd'=>['period'=>'all','currency'=>'USD'],
    'client'=>['period'=>'all','party_type'=>'client'], 'client-usd'=>['period'=>'all','party_type'=>'client','currency'=>'USD'], 'vendor'=>['period'=>'all','party_type'=>'vendor'],
    'search'=>['period'=>'all','party_type'=>'client','q'=>'Historical','currency'=>'USD'], 'search-all'=>['period'=>'all','party_type'=>'client','q'=>'Historical']];
foreach ($variants as $name => $query) {
    $request = \Illuminate\Http\Request::create('/cashflows/statements','GET',$query);
    $route = app('router')->getRoutes()->match($request);
    $request->setRouteResolver(fn () => $route);
    $request->setUserResolver(fn () => $fixture['user']);
    app()->instance('request',$request);
    app('url')->setRequest($request);
    view()->share('errors',new \Illuminate\Support\ViewErrorBag);
    file_put_contents($output.'/'.$name.'.html', app(\App\Http\Controllers\PartyStatementController::class)->index($request)->render());
}
echo "Rendered fictional statement list variants (disposable SQLite only).\n";
