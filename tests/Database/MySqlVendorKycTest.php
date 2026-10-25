<?php

namespace Tests\Database;

use App\Models\User;
use App\Models\Vendor;
use App\Services\VendorKyc;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\Support\VendorKycFixture;
use Tests\TestCase;

/** No SQLite substitution: real committed rows and a competing MySQL connection. */
class MySqlVendorKycTest extends TestCase
{
    public function test_native_json_schema_locking_and_stale_review_protection(): void
    {
        $primary = DB::getDefaultConnection();
        $db = DB::connection();
        if (! app()->environment('testing') || $db->getDriverName() !== 'mysql' || ! str_ends_with($db->getDatabaseName(), '_test')) {
            throw new \LogicException('Vendor KYC native checks require a disposable MySQL *_test database.');
        }
        Mail::fake();
        $suffix = bin2hex(random_bytes(6));
        $users = [];
        $vendor = null;
        config(['database.connections.kyc_competitor' => config('database.connections.'.$primary)]);
        try {
            foreach (['maker', 'checker'] as $role) {
                $users[$role] = User::create(['name' => 'Native KYC '.$role,
                    'email' => $suffix.'-'.$role.'@example.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'full']);
            }
            $vendor = Vendor::create(['vendor_number' => 'NATIVE-KYC-'.$suffix, 'vendor_name' => 'Native original',
                'created_by' => $users['maker']->id, 'status' => 'blacklisted', 'preferred_currency' => 'RMB', 'account_number' => 'OLD']);
            $service = app(VendorKyc::class);
            $service->link($vendor, $users['maker'], 'issue');
            $vendor->refresh();
            $request = Request::create('/vendor-kyc/'.$vendor->kyc_public_token, 'POST', VendorKycFixture::payload([
                'intent' => 'submit', 'kyc_version' => $vendor->kyc_version, 'vendor_name' => 'Native approved partner',
            ]));
            $service->savePublic($vendor->kyc_public_token, $request);
            $vendor->refresh();
            $this->assertSame('Native original', $vendor->vendor_name);
            $this->assertSame('NEW-BANK', $vendor->kyc_submitted_data['account_number']);
            $version = $vendor->kyc_version;
            $competitor = DB::connection('kyc_competitor');
            $competitor->statement('SET SESSION innodb_lock_wait_timeout = 1');
            $db->beginTransaction();
            Vendor::whereKey($vendor->id)->lockForUpdate()->firstOrFail();
            DB::setDefaultConnection('kyc_competitor');
            try {
                $service->review($vendor, $users['checker'], ['decision' => 'approved', 'kyc_version' => $version]);
                $this->fail('A competing review must not bypass the vendor row lock.');
            } catch (QueryException $e) {
                $this->assertSame(1205, (int) ($e->errorInfo[1] ?? 0), 'Competing review waits for the actual InnoDB row lock.');
            } finally {
                DB::setDefaultConnection($primary);
                $db->rollBack();
            }
            $this->assertSame('under_review', $vendor->fresh()->kyc_status);
            $service->review($vendor, $users['checker'], ['decision' => 'approved', 'kyc_version' => $version]);
            $vendor->refresh();
            $this->assertSame('approved', $vendor->kyc_status);
            $this->assertSame('Native approved partner', $vendor->vendor_name);
            $this->assertSame('blacklisted', $vendor->status);
            $this->assertSame('RMB', $vendor->preferred_currency);
            $this->assertSame('NEW-BANK', $vendor->account_number);
            try {
                $service->review($vendor, $users['checker'], ['decision' => 'rejected', 'reason' => 'stale', 'kyc_version' => $version]);
                $this->fail('A replayed review must not overwrite an approval.');
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('decision', $e->errors());
            }
            $this->assertSame(1, DB::table('vendor_kyc_events')->where('vendor_id', $vendor->id)->where('action', 'approved')->count());
            $snapshot = DB::table('vendor_kyc_events')->where('vendor_id', $vendor->id)->where('action', 'submitted')->value('snapshot');
            $this->assertSame('NEW-BANK', json_decode($snapshot, true)['profile']['account_number']);
        } finally {
            DB::setDefaultConnection($primary);
            while ($db->transactionLevel() > 0) {
                $db->rollBack();
            }
            DB::disconnect('kyc_competitor');
            DB::purge('kyc_competitor');
            if ($vendor) {
                DB::table('office_alerts')->where('subject_type', Vendor::class)->where('subject_id', $vendor->id)->delete();
                $vendor->delete();
            }
            foreach ($users as $user) {
                $user->delete();
            }
        }
    }
}
