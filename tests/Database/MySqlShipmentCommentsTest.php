<?php

namespace Tests\Database;

use App\Models\Shipment;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Real InnoDB foreign keys and two-process shipment/comment locking on a disposable database. */
class MySqlShipmentCommentsTest extends TestCase
{
    private string $privateRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Shipment comment checks require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if ((! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) || ! str_contains($mode, 'ONLY_FULL_GROUP_BY')) {
            throw new \LogicException('Shipment comment checks require strict MySQL and ONLY_FULL_GROUP_BY.');
        }
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)), 'cache.default' => 'array',
            'session.driver' => 'array', 'hashing.bcrypt.rounds' => 4]);
        if (! is_dir(base_path('.work'))) mkdir(base_path('.work'), 0755, true);
        $this->privateRoot = base_path('.work/mysql-shipment-comments-'.bin2hex(random_bytes(6)));
        mkdir($this->privateRoot, 0755, true);
        config(['filesystems.disks.local.root' => $this->privateRoot]);
        Storage::purge('local');
    }

    private function makeActorAndShipment(): array
    {
        $suffix = bin2hex(random_bytes(4));
        $user = User::create(['name' => 'Native shipping user', 'email' => 'shipment-native-'.$suffix.'@example.test',
            'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['operations.manage']]);
        $shipment = Shipment::create(['shipment_number' => 'NATIVE-SHIP-'.$suffix, 'identity_name' => 'Native fixture',
            'shipment_type' => 'domestic', 'status' => 'planning', 'currency' => 'INR']);
        return [$user, $shipment];
    }

    /** @return array{resource,array} */
    private function worker(string $operation, Shipment $shipment, User $user): array
    {
        $php = getenv('TEST_PHP_BINARY') ?: PHP_BINARY;
        if (! $php) throw new \LogicException('Set TEST_PHP_BINARY for the native shipment concurrency worker.');
        $process = proc_open([$php, base_path('tests/Support/ShipmentCommentsConcurrencyWorker.php'), $operation,
            (string) $shipment->id, (string) $user->id, $this->privateRoot],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
        if (! is_resource($process)) throw new \RuntimeException('Could not start the shipment concurrency worker.');
        fclose($pipes[0]);
        stream_set_timeout($pipes[1], 15);
        try {
            $this->assertSame("READY\n", fgets($pipes[1]));
            usleep(300000);
            $this->assertTrue(proc_get_status($process)['running'], 'The second operation must wait for the shipment lock.');
        } catch (\Throwable $error) {
            $this->stopWorker($process, $pipes);
            throw $error;
        }
        return [$process, $pipes];
    }

    private function finishWorker(&$process, array $pipes, string $expected): void
    {
        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($process); $process = null;
        $this->assertSame(0, $exit, $error);
        $this->assertStringContainsString($expected, $output);
    }

    private function stopWorker(&$process, array $pipes): void
    {
        if (! is_resource($process)) return;
        proc_terminate($process);
        foreach ($pipes as $pipe) if (is_resource($pipe)) fclose($pipe);
        proc_close($process); $process = null;
    }

    public function test_native_schema_foreign_keys_private_files_and_lossy_rollback_guard(): void
    {
        $db = DB::connection();
        $manager = new DatabaseTransactionsManager([$db->getName()]);
        $this->app->instance('db.transactions', $manager);
        $db->setTransactionManager($manager);
        $db->beginTransaction();
        try {
            [$actor, $shipment] = $this->makeActorAndShipment();
            $this->actingAs($actor)->post(route('shipments.comments.store', $shipment), ['body' => str_repeat('x', 4000),
                'attachments' => [UploadedFile::fake()->create('native.pdf', 1, 'application/pdf')]])
                ->assertRedirect(route('shipments.show', ['shipment' => $shipment, 'tab' => 'comments']))->assertSessionHasNoErrors();
            $comment = $shipment->comments()->firstOrFail();
            $file = $comment->attachments()->firstOrFail();
            $this->assertSame(4000, strlen($comment->body));
            Storage::disk('local')->assertExists($file->file_path);
            $this->get(route('shipments.comments.attachments.file', [$shipment, $comment, $file]))->assertOk()
                ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
            try {
                DB::table('shipment_comments')->insert(['shipment_id' => 999999999, 'created_by' => $actor->id, 'body' => 'invalid parent']);
                $this->fail('InnoDB must enforce the shipment foreign key.');
            } catch (QueryException $error) {
                $this->assertSame('23000', $error->errorInfo[0]);
            }
            $migration = require database_path('migrations/2026_11_02_100000_create_shipment_internal_comments.php');
            try {
                $migration->down();
                $this->fail('A rollback must not drop live discussions or strand private files.');
            } catch (\RuntimeException $error) {
                $this->assertStringContainsString('Cannot roll back shipment comments', $error->getMessage());
            }
            $this->assertDatabaseHas('shipment_comment_attachments', ['id' => $file->id]);
            $author = User::create(['name' => 'Former native user', 'email' => 'former-'.bin2hex(random_bytes(4)).'@example.test',
                'password' => 'fixture', 'role' => 'admin']);
            $comment->update(['created_by' => $author->id]);
            $author->delete();
            $this->assertNull($comment->fresh()->created_by);
            $this->delete(route('shipments.comments.destroy', [$shipment, $comment]))->assertRedirect();
            $this->assertDatabaseMissing('shipment_comments', ['id' => $comment->id]);
            $this->assertDatabaseMissing('shipment_comment_attachments', ['id' => $file->id]);
            Storage::disk('local')->assertMissing($file->file_path);
        } finally {
            while ($db->transactionLevel() > 0) $db->rollBack();
        }
    }

    public function test_comment_upload_waiting_for_shipment_deletion_cannot_store_a_file_or_orphan_a_comment(): void
    {
        [$actor, $shipment] = $this->makeActorAndShipment();
        $process = null; $pipes = [];
        try {
            DB::beginTransaction();
            Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            [$process, $pipes] = $this->worker('store', $shipment, $actor);
            DB::table('shipments')->where('id', $shipment->id)->delete();
            DB::commit();
            $this->finishWorker($process, $pipes, 'MISSING_PARENT');
            $this->assertDatabaseMissing('shipments', ['id' => $shipment->id]);
            $this->assertSame(0, DB::table('shipment_comments')->where('shipment_id', $shipment->id)->count());
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            $this->stopWorker($process, $pipes);
            DB::table('shipments')->where('id', $shipment->id)->delete();
            $actor->delete();
        }
    }

    public function test_shipment_deletion_waits_for_a_committing_comment_and_cleans_its_new_file(): void
    {
        [$actor, $shipment] = $this->makeActorAndShipment();
        $process = null; $pipes = [];
        try {
            DB::beginTransaction();
            $locked = Shipment::query()->whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $comment = $locked->comments()->create(['body' => 'Commit while delete waits', 'created_by' => $actor->id]);
            $path = 'shipments/comments/'.$shipment->id.'/race.pdf';
            Storage::disk('local')->put($path, "%PDF-1.4\n%%EOF\n");
            $file = $comment->attachments()->create(['file_path' => $path, 'original_name' => 'race.pdf',
                'mime_type' => 'application/pdf', 'file_size' => 15]);
            [$process, $pipes] = $this->worker('destroy', $shipment, $actor);
            DB::commit();
            $this->finishWorker($process, $pipes, 'DELETED');
            $this->assertDatabaseMissing('shipments', ['id' => $shipment->id]);
            $this->assertDatabaseMissing('shipment_comments', ['id' => $comment->id]);
            $this->assertDatabaseMissing('shipment_comment_attachments', ['id' => $file->id]);
            Storage::disk('local')->assertMissing($path);
        } finally {
            while (DB::transactionLevel() > 0) DB::rollBack();
            $this->stopWorker($process, $pipes);
            DB::table('shipments')->where('id', $shipment->id)->delete();
            Storage::disk('local')->deleteDirectory('shipments/comments/'.$shipment->id);
            $actor->delete();
        }
    }
}
