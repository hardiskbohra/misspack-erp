<?php

namespace Tests\Database;

use App\Models\Client;
use App\Models\PartyStatementShare;
use App\Models\Shipment;
use App\Models\Vendor;
use App\Services\PublicShareLinks;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Guest HTTP resolution and the empty client statement on native, strict MySQL. */
class MySqlPublicShortLinksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32)),
            'app.previous_keys' => [], 'cache.default' => 'array', 'session.driver' => 'array']);
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Public short-link checks require a disposable MySQL *_test database.');
        }
        $mode = (string) $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        if (! str_contains($mode, 'STRICT_TRANS_TABLES') && ! str_contains($mode, 'STRICT_ALL_TABLES')) {
            throw new \LogicException('Public short-link checks require strict MySQL SQL mode.');
        }
        $manager = new DatabaseTransactionsManager([$db->getName()]);
        $this->app->instance('db.transactions', $manager);
        $db->setTransactionManager($manager);
        $db->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (app()->bound('db')) {
            $db = DB::connection();
            while ($db->transactionLevel() > 0) $db->rollBack();
        }
        parent::tearDown();
    }

    public function test_guest_links_use_current_tokens_and_the_original_handlers_on_mysql(): void
    {
        $client = Client::create(['client_number' => 'MYSQL-SHORT-CLIENT', 'company_name' => 'Native Short Client']);
        $vendor = Vendor::create(['vendor_number' => 'MYSQL-SHORT-VENDOR', 'vendor_name' => 'Native Short Vendor']);
        $vendor->forceFill(['kyc_public_token' => Str::random(48)])->save();
        $shipment = Shipment::create(['shipment_number' => 'MYSQL-SHORT-SHIP', 'identity_name' => 'Native Short Shipment']);
        $statement = PartyStatementShare::issue(['party_type' => 'client', 'party_id' => $client->id, 'party_name' => $client->company_name]);

        foreach ([[$client, 'clients.publicKyc', $client->public_token], [$vendor, 'vendors.publicKyc', $vendor->kyc_public_token],
            [$shipment, 'shipments.publicTrack', $shipment->public_token], [$statement, 'statements.public', $statement->token]] as [$model, $route, $token]) {
            $short = PublicShareLinks::url($model);
            $this->assertStringNotContainsString($token, $short);
            $this->get($short)->assertStatus(302)->assertHeader('Location', parse_url(route($route, $token), PHP_URL_PATH));
            $this->get(route($route, $token))->assertOk();
        }
        $this->assertSame(1, $statement->fresh()->views);
        $this->get('/s/c18446744073709551615.'.str_repeat('A', 22))->assertNotFound();
        $this->get('/s/c'.str_repeat('9', 20).'.'.str_repeat('A', 22))->assertNotFound();
        $this->get('/s/c'.str_repeat('9', 21).'.'.str_repeat('A', 22))->assertNotFound();
        $old = PublicShareLinks::url($client);
        $client->forceFill(['public_token' => Str::random(48)])->save();
        $this->get($old)->assertNotFound();
        $this->get(PublicShareLinks::url($client))->assertStatus(302);
    }
}
