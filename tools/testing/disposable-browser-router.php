<?php

/** Built-in server router for browser tests using fictional, disposable data only. */
$root = dirname(__DIR__, 2);
$work = realpath($root.'/.work');
$database = realpath((string) getenv('DB_DATABASE'));
$storage = realpath((string) getenv('LARAVEL_STORAGE_PATH'));
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'sqlite' || ! $work || ! $database || ! $storage
    || ! str_starts_with($database, $work.DIRECTORY_SEPARATOR) || ! str_starts_with($storage, $work.DIRECTORY_SEPARATOR)) {
    http_response_code(503);
    exit('Disposable testing SQLite database and storage under .work required.');
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$public = realpath($root.'/public');
$asset = realpath($root.'/public'.$path);
if ($path !== '/' && $asset && str_starts_with($asset, $public.DIRECTORY_SEPARATOR) && is_file($asset)) return false;
require $root.'/public/index.php';
