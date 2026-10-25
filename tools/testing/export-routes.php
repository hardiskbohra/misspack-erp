<?php

/** App-declared routing inventory only; never sends requests or reads customer records. */
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$routes = [];
foreach (Illuminate\Support\Facades\Route::getRoutes() as $route) {
    $routes[] = [
        'name' => $route->getName(), 'methods' => $route->methods(), 'uri' => $route->uri(),
        'action' => $route->getActionName(),
        'middleware' => array_map(fn ($middleware) => is_string($middleware) ? $middleware : get_debug_type($middleware), $route->gatherMiddleware()),
    ];
}
usort($routes, fn ($a, $b) => [$a['uri'], implode(',', $a['methods'])] <=> [$b['uri'], implode(',', $b['methods'])]);
$contents = json_encode(['note' => 'Declared routes and middleware, not evidence of executed requests or correct record ownership.', 'routes' => $routes], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
$file = __DIR__.'/../../docs/testing/route-inventory.json';
if (in_array('--check', $argv, true)) {
    if (! is_file($file) || file_get_contents($file) !== $contents) {
        fwrite(STDERR, "Route inventory is stale. Run php tools/testing/export-routes.php.\n");
        exit(1);
    }
} else {
    file_put_contents($file, $contents);
}
echo count($routes)." routes inventoried (no runtime access/coverage claim).\n";
