<?php

/** PHP built-in server router for the disposable, fixed-clock browser check only. */
$root = dirname(__DIR__, 2);
$work = realpath($root.'/.work');
$database = realpath((string) getenv('DB_DATABASE'));
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite'
    || ! $work || ! $database || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR)) {
    http_response_code(503);
    exit('Disposable testing SQLite database under .work required.');
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$public = realpath($root.'/public');
$asset = realpath($root.'/public'.$path);
if ($path !== '/' && $asset && str_starts_with($asset, $public.DIRECTORY_SEPARATOR) && is_file($asset)) return false;

require $root.'/vendor/autoload.php';
\Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-10-29 10:00:00', 'Asia/Kolkata'));
require $root.'/public/index.php';
