<?php

namespace Tests\Database;

use App\Models\Product;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockLedger;
use App\Services\StockReconciliation;
use App\Services\StockRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Native upgrade, product-reference and archive serialization safeguards. */
class MySqlStockRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Stock checks require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if ((! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) || ! str_contains($mode, 'ONLY_FULL_GROUP_BY')) {
            throw new \LogicException('Stock checks require strict MySQL and ONLY_FULL_GROUP_BY.');
        }
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
    }

    public function test_native_upgrade_backfills_existing_stock_and_preserves_its_first_identity(): void
    {
        // MySQL DDL auto-commits. Use randomly prefixed fixture tables on a
        // separate connection, never the real schema's tables or its migrations.
        $originalConnection = DB::getDefaultConnection();
        $temporaryConnection = 'stock_upgrade_fixture';
        $prefix = 'u'.bin2hex(random_bytes(3)).'_';
        $config = config('database.connections.'.$originalConnection);
        $config['prefix'] = $prefix;
        $config['prefix_indexes'] = true;
        config(['database.connections.'.$temporaryConnection => $config]);
        DB::purge($temporaryConnection);
        DB::setDefaultConnection($temporaryConnection);
        Schema::clearResolvedInstance('db.schema');
        $tables = ['stock_item_events', 'stock_item_product_history', 'stock_movements', 'stock_count_events',
            'stock_count_lines', 'stock_counts', 'stock_items', 'products', 'users'];
        $safeToClean = false;
        try {
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) throw new \LogicException('Refusing to reuse an existing upgrade fixture table.');
            }
            $safeToClean = true;
            Schema::create('users', function (Blueprint $table) { $table->id(); $table->string('name'); });
            Schema::create('products', function (Blueprint $table) {
                $table->id(); $table->string('product_number'); $table->string('name'); $table->string('sku')->nullable();
            });
            (require database_path('migrations/2026_11_04_100000_create_stock_reconciliation_tables.php'))->up();
            $userId = DB::table('users')->insertGetId(['name' => 'Fictional upgrade actor']);
            $oldProductId = DB::table('products')->insertGetId(['product_number' => 'OLD', 'name' => 'Original bottle']);
            $newProductId = DB::table('products')->insertGetId(['product_number' => 'NEW', 'name' => 'Correct bottle']);
            $now = now();
            $itemId = DB::table('stock_items')->insertGetId(['product_id' => $oldProductId, 'variant' => 'Legacy',
                'batch_code' => 'LOT-OLD', 'unit' => 'pcs', 'identity_hash' => StockLedger::identityHash($oldProductId, 'Legacy', 'LOT-OLD'),
                'quantity_milli' => 10000, 'ledger_version' => 1, 'created_by' => $userId, 'creator_name' => 'Fictional upgrade actor',
                'created_at' => $now, 'updated_at' => $now]);
            DB::table('stock_movements')->insert(['stock_item_id' => $itemId, 'type' => 'opening', 'delta_milli' => 10000,
                'balance_after_milli' => 10000, 'ledger_version' => 1, 'reason' => 'Legacy opening', 'request_key' => 'legacy:'.$itemId,
                'created_by' => $userId, 'actor_name' => 'Fictional upgrade actor', 'created_at' => $now, 'updated_at' => $now]);

            $migration = require database_path('migrations/2026_11_04_110000_add_stock_register_edit_archive.php');
            $migration->up();
            $this->assertSame((int) $oldProductId, (int) DB::table('stock_item_product_history')->where('stock_item_id', $itemId)->value('product_id'));
            $item = StockItem::findOrFail($itemId);
            $this->assertNull($item->movements()->firstOrFail()->item_snapshot);
            app(StockRegistry::class)->update($item, ['product_id' => $newProductId, 'variant' => 'Corrected', 'batch_code' => 'LOT-OLD',
                'unit' => 'pcs', 'requested_quantity' => '10.000', 'reason' => 'Correct legacy identity',
                'record_revision' => 0, 'ledger_version' => 1], User::findOrFail($userId));
            $item->refresh();
            $legacy = $item->movements()->firstOrFail()->item_snapshot;
            $this->assertSame('Legacy', $legacy['variant']);
            $this->assertSame('legacy_pre_edit', $legacy['snapshot_basis']);
            $this->assertSame('10.000', $item->quantity_on_hand);
            $this->assertSame(2, DB::table('stock_item_product_history')->where('stock_item_id', $itemId)->count());
            try {
                DB::table('products')->where('id', $oldProductId)->delete();
                $this->fail('The original product must stay protected after correction.');
            } catch (QueryException $error) {
                $this->assertSame('23000', $error->errorInfo[0]);
            }
            try {
                $migration->down();
                $this->fail('Rollback must not discard the upgraded history.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('Cannot roll back stock register', $error->getMessage());
            }
        } finally {
            try {
                if ($safeToClean) foreach ($tables as $table) Schema::dropIfExists($table);
            } finally {
                DB::setDefaultConnection($originalConnection);
                Schema::clearResolvedInstance('db.schema');
                DB::purge($temporaryConnection);
            }
        }
    }

    public function test_native_foreign_key_keeps_an_original_product_after_item_reassignment(): void
    {
        $db = DB::connection();
        $db->beginTransaction();
        try {
            $suffix = bin2hex(random_bytes(6));
            $actor = User::create(['name' => 'Native registry user', 'email' => "history-{$suffix}@example.test", 'password' => 'fixture',
                'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
            $original = Product::create(['name' => 'Original product', 'product_number' => 'HIST-A-'.$suffix]);
            $corrected = Product::create(['name' => 'Corrected product', 'product_number' => 'HIST-B-'.$suffix]);
            $item = app(StockLedger::class)->createItem(['product_id' => $original->id, 'variant' => 'Amber', 'batch_code' => 'L-1',
                'unit' => 'pcs', 'opening_quantity' => '10', 'reason' => 'Fictional opening'], $actor);
            app(StockRegistry::class)->update($item, ['product_id' => $corrected->id, 'variant' => 'Amber', 'batch_code' => 'L-1',
                'unit' => 'pcs', 'requested_quantity' => '10', 'reason' => 'Correct product',
                'record_revision' => $item->record_revision, 'ledger_version' => $item->ledger_version], $actor);
            $this->assertSame($corrected->id, $item->fresh()->product_id);
            try {
                $original->delete();
                $this->fail('The original product is still referenced by stock history.');
            } catch (QueryException $error) {
                $this->assertSame('23000', $error->errorInfo[0]);
            }
            $this->assertDatabaseHas('products', ['id' => $original->id]);
        } finally {
            $db->rollBack();
        }
    }

    public function test_archive_waiting_for_count_creation_observes_the_open_count(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $actor = User::create(['name' => 'Native registry counter', 'email' => "count-registry-{$suffix}@example.test", 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $product = Product::create(['name' => 'Native count bottle', 'product_number' => 'COUNT-REG-'.$suffix]);
        $item = app(StockLedger::class)->createItem(['product_id' => $product->id, 'variant' => 'Amber', 'batch_code' => 'L-1',
            'unit' => 'pcs', 'opening_quantity' => '10', 'reason' => 'Fictional opening'], $actor);
        $db = DB::connection(); $count = null; $process = null; $pipes = [];
        try {
            $db->beginTransaction();
            StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the registry concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/StockRegistryConcurrencyWorker.php'), (string) $item->id,
                (string) $actor->id, (string) $item->record_revision, (string) $item->ledger_version],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the registry concurrency worker.');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            usleep(300000);
            $this->assertTrue(proc_get_status($process)['running'], 'Archive must wait for count creation to release the item.');
            $count = app(StockReconciliation::class)->create(['title' => 'Concurrent open count', 'item_ids' => [$item->id]], $actor);
            $db->commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('REJECTED Finish or cancel', $output);
            $this->assertNull($item->fresh()->archived_at);
            $this->assertSame('draft', $count->fresh()->status);
            $this->assertSame('10.000', $item->fresh()->quantity_on_hand);
        } finally {
            if ($db->transactionLevel() > 0) $db->rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('stock_item_events')->where('stock_item_id', $item->id)->delete();
            if ($count) {
                DB::table('stock_count_events')->where('stock_count_id', $count->id)->delete();
                DB::table('stock_count_lines')->where('stock_count_id', $count->id)->delete();
                DB::table('stock_counts')->where('id', $count->id)->delete();
            }
            DB::table('stock_movements')->where('stock_item_id', $item->id)->delete();
            DB::table('stock_item_product_history')->where('stock_item_id', $item->id)->delete();
            DB::table('stock_items')->where('id', $item->id)->delete();
            DB::table('products')->where('id', $product->id)->delete();
            DB::table('users')->where('id', $actor->id)->delete();
        }
    }

    public function test_archive_waiting_for_a_movement_rejects_the_stale_balance_and_keeps_history(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $actor = User::create(['name' => 'Native registry user', 'email' => "registry-{$suffix}@example.test", 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $product = Product::create(['name' => 'Native registry bottle', 'product_number' => 'NATIVE-REG-'.$suffix]);
        $item = app(StockLedger::class)->createItem(['product_id' => $product->id, 'variant' => 'Amber', 'batch_code' => 'L-1',
            'unit' => 'pcs', 'opening_quantity' => '10', 'reason' => 'Fictional opening'], $actor);
        $db = DB::connection(); $process = null; $pipes = [];
        try {
            $db->beginTransaction();
            StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the registry concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/StockRegistryConcurrencyWorker.php'), (string) $item->id,
                (string) $actor->id, (string) $item->record_revision, (string) $item->ledger_version],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the registry concurrency worker.');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            usleep(300000);
            $this->assertTrue(proc_get_status($process)['running'], 'Archive must wait for the item lock.');
            app(StockLedger::class)->manualMovement($item, ['type' => 'receipt', 'quantity' => '1', 'reason' => 'Concurrent receipt',
                'reference' => null, 'record_revision' => $item->record_revision, 'request_key' => (string) Str::uuid()], $actor);
            $db->commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('REJECTED', $output);
            $this->assertNull($item->fresh()->archived_at);
            $this->assertSame('11.000', $item->fresh()->quantity_on_hand);
            $this->assertSame(2, $item->movements()->count());
        } finally {
            if ($db->transactionLevel() > 0) $db->rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('stock_item_events')->where('stock_item_id', $item->id)->delete();
            DB::table('stock_movements')->where('stock_item_id', $item->id)->delete();
            DB::table('stock_item_product_history')->where('stock_item_id', $item->id)->delete();
            DB::table('stock_items')->where('id', $item->id)->delete();
            DB::table('products')->where('id', $product->id)->delete();
            DB::table('users')->where('id', $actor->id)->delete();
        }
    }
}
