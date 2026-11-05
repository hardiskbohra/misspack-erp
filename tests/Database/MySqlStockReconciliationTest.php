<?php

namespace Tests\Database;

use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockLedger;
use App\Services\StockReconciliation;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Native InnoDB foreign-key, locking and exactly-once safeguards. */
class MySqlStockReconciliationTest extends TestCase
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

    private function fixture(): array
    {
        $suffix = bin2hex(random_bytes(6));
        $actor = User::create(['name' => 'Native stock user', 'email' => "stock-{$suffix}@example.test", 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $product = Product::create(['name' => 'Native stock bottle', 'product_number' => 'NATIVE-STOCK-'.$suffix]);
        $item = app(StockLedger::class)->createItem(['product_id' => $product->id, 'variant' => 'Amber 100ml',
            'batch_code' => 'L-1', 'expires_on' => '2027-01-01', 'unit' => 'pcs', 'opening_quantity' => '10.000',
            'reason' => 'Native test opening'], $actor);
        return [$actor, $product, $item];
    }

    public function test_native_foreign_keys_exact_decimal_and_populated_rollback_guard(): void
    {
        $db = DB::connection();
        $db->beginTransaction();
        try {
            [$actor, $product, $item] = $this->fixture();
            $this->assertSame('10.000', $item->fresh()->quantity_on_hand);
            try {
                $product->delete();
                $this->fail('Stock history must protect its product from deletion.');
            } catch (QueryException $error) {
                $this->assertSame('23000', $error->errorInfo[0]);
            }
            try {
                (require database_path('migrations/2026_11_04_100000_create_stock_reconciliation_tables.php'))->down();
                $this->fail('Populated stock history must block rollback.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('Cannot roll back stock', $error->getMessage());
            }
            $this->assertDatabaseHas('stock_items', ['id' => $item->id, 'product_id' => $product->id]);
        } finally {
            $db->rollBack();
        }
    }

    public function test_simultaneous_count_approvals_post_one_adjustment(): void
    {
        [$counter, $product, $item] = $this->fixture();
        $reviewer = User::create(['name' => 'Native reviewer', 'email' => 'review-'.bin2hex(random_bytes(6)).'@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $service = app(StockReconciliation::class);
        $count = null; $process = null; $pipes = [];
        $db = DB::connection();
        try {
            $count = $service->create(['title' => 'Native competing approvals', 'item_ids' => [$item->id]], $counter);
            $line = $count->lines()->firstOrFail();
            $service->save($count, [$line->id => ['counted_qty' => '9.000', 'reason' => 'One damaged']], $counter, true, $count->revision);
            $count->refresh();
            $db->beginTransaction();
            StockCount::whereKey($count->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the count concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/StockCountConcurrencyWorker.php'), (string) $count->id,
                (string) $reviewer->id, (string) $count->revision], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the count concurrency worker.');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            usleep(300000);
            $this->assertTrue(proc_get_status($process)['running'], 'The competing approval must wait for the count lock.');
            $service->approve($count, $reviewer, 'First approval', $count->revision);
            $db->commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('REJECTED', $output);
            $this->assertSame('posted', $count->fresh()->status);
            $this->assertSame('9.000', $item->fresh()->quantity_on_hand);
            $this->assertSame(1, $item->movements()->where('type', 'count_adjustment')->count());
            $this->assertSame(1, $count->events()->where('action', 'posted')->count());
        } finally {
            if ($db->transactionLevel() > 0) $db->rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('stock_movements')->where('stock_item_id', $item->id)->delete();
            if ($count) {
                DB::table('stock_count_events')->where('stock_count_id', $count->id)->delete();
                DB::table('stock_count_lines')->where('stock_count_id', $count->id)->delete();
                DB::table('stock_counts')->where('id', $count->id)->delete();
            }
            DB::table('stock_item_product_history')->where('stock_item_id', $item->id)->delete();
            DB::table('stock_items')->where('id', $item->id)->delete();
            DB::table('products')->where('id', $product->id)->delete();
            DB::table('users')->whereIn('id', [$counter->id, $reviewer->id])->delete();
        }
    }

    public function test_competing_issues_are_serialized_and_cannot_make_stock_negative(): void
    {
        [$actor, $product, $item] = $this->fixture();
        $db = DB::connection();
        $process = null; $pipes = [];
        try {
            $db->beginTransaction();
            StockItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the stock concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/StockConcurrencyWorker.php'), (string) $item->id, (string) $actor->id],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the stock concurrency worker.');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $this->assertSame("READY\n", fgets($pipes[1]));
            usleep(300000);
            $this->assertTrue(proc_get_status($process)['running'], 'The second issue must wait for the item lock.');
            app(StockLedger::class)->manualMovement($item, ['type' => 'issue', 'quantity' => '6.000',
                'reason' => 'First issue', 'reference' => null, 'record_revision' => $item->record_revision,
                'request_key' => (string) Str::uuid()], $actor);
            $db->commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('REJECTED', $output);
            $this->assertSame('4.000', $item->fresh()->quantity_on_hand);
            $this->assertSame(2, $item->movements()->count());
        } finally {
            if ($db->transactionLevel() > 0) $db->rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('stock_movements')->where('stock_item_id', $item->id)->delete();
            DB::table('stock_item_product_history')->where('stock_item_id', $item->id)->delete();
            DB::table('stock_items')->where('id', $item->id)->delete();
            DB::table('products')->where('id', $product->id)->delete();
            DB::table('users')->where('id', $actor->id)->delete();
        }
    }
}
