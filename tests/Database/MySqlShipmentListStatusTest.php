<?php

namespace Tests\Database;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** An ordered race for the list's expected-status guard on real MySQL locks. */
class MySqlShipmentListStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Shipment checks require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if ((! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) || ! str_contains($mode, 'ONLY_FULL_GROUP_BY')) {
            throw new \LogicException('Shipment checks require strict MySQL and ONLY_FULL_GROUP_BY.');
        }
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
    }

    public function test_waiting_list_update_observes_a_newer_status_under_the_shipment_lock(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $user = User::create(['name' => 'Fictional shipping manager', 'email' => "list-{$suffix}@example.test", 'password' => 'fixture',
            'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $shipment = Shipment::create(['shipment_number' => 'LIST-RACE-'.$suffix, 'identity_name' => 'Fictional shipment',
            'shipment_type' => 'domestic', 'status' => 'in_transit', 'currency' => 'INR']);
        $db = DB::connection(); $process = null; $pipes = [];
        try {
            $db->beginTransaction();
            $locked = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
            if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the shipment concurrency worker.');
            $process = proc_open([$php, base_path('tests/Support/ShipmentListStatusConcurrencyWorker.php'), (string) $shipment->id, (string) $user->id],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
            if (! is_resource($process)) throw new \RuntimeException('Could not start the shipment status worker.');
            fclose($pipes[0]);
            stream_set_timeout($pipes[1], 15);
            $ready = fgets($pipes[1]);
            if ($ready !== "READY\n") {
                stream_set_blocking($pipes[1], false);
                stream_set_blocking($pipes[2], false);
                $diagnostic = (string) $ready . stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                $this->fail('The shipment status worker did not reach its barrier: '.trim($diagnostic));
            }
            $this->assertSame("READY\n", $ready);
            usleep(300000);
            $this->assertTrue(proc_get_status($process)['running'], 'The list update must wait for the shipment lock.');
            $locked->update(['status' => 'out_for_delivery']);
            $db->commit();
            $output = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            $exit = proc_close($process); $process = null;
            $this->assertSame(0, $exit, $error);
            $this->assertStringContainsString('REJECTED', $output);
            $this->assertStringContainsString('changed since', $output);
            $this->assertSame('out_for_delivery', $shipment->fresh()->status);
            $this->assertSame(0, $shipment->histories()->count());
        } finally {
            if ($db->transactionLevel() > 0) $db->rollBack();
            if (is_resource($process)) {
                proc_terminate($process);
                foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
                proc_close($process);
            }
            DB::table('shipment_tracking_histories')->where('shipment_id', $shipment->id)->delete();
            DB::table('shipments')->where('id', $shipment->id)->delete();
            DB::table('users')->where('id', $user->id)->delete();
        }
    }
}
