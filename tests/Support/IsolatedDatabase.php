<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/** Opt-in, disposable SQLite fixtures; deliberately not a substitute for fresh-migration coverage. */
trait IsolatedDatabase
{
    protected function isolatedDatabase(array $migrations = []): void
    {
        if (! app()->environment('testing')) throw new \LogicException('Test fixtures require APP_ENV=testing.');
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4,
            'app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        DB::purge('sqlite');
        foreach ($migrations as $migration) (require database_path('migrations/'.$migration.'.php'))->up();
    }
}
