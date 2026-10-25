<?php

namespace Tests\Support;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class VendorKycFixture
{
    public const MIGRATION = '2026_10_25_180000_add_vendor_kyc.php';

    public static function install(): array
    {
        AnalyticsDatabaseFixture::install();
        $migration = database_path('migrations/'.self::MIGRATION);
        (require $migration)->up();
        foreach (['2026_10_06_020000_create_office_alerts_table', '2026_10_06_030000_add_shared_ack_to_office_alerts', '2026_10_06_040000_add_snooze_and_email_to_office_alerts', '2026_10_06_050000_create_office_settings_table'] as $file) {
            (require database_path('migrations/'.$file.'.php'))->up();
        }
        Mail::fake();
        Storage::fake('local');
        $maker = User::create(['name' => 'Vendor maker', 'email' => 'maker@kyc.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['purchase.manage']]);
        $checker = User::create(['name' => 'Vendor checker', 'email' => 'checker@kyc.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['purchase.manage']]);
        $reader = User::create(['name' => 'Purchase reader', 'email' => 'reader@kyc.test', 'password' => 'fixture', 'role' => 'admin', 'access_profile' => 'custom', 'permissions' => ['purchase.view']]);
        $vendor = Vendor::create(['vendor_number' => 'KYC-001', 'vendor_name' => 'Original supplier', 'vendor_type' => 'manufacturer',
            'status' => 'on_hold', 'preferred_currency' => 'RMB', 'created_by' => $maker->id, 'notes' => 'PRIVATE OFFICE NOTE', 'rating' => 5, 'account_number' => 'OLD-BANK']);

        return compact('maker', 'checker', 'reader', 'vendor');
    }

    public static function payload(array $changes = []): array
    {
        return array_replace(['vendor_name' => 'Submitted packaging partner', 'vendor_type' => 'packaging_supplier',
            'contact_person_name' => 'Partner contact', 'contact_person_email' => 'partner@example.test', 'contact_person_mobile' => '+91 9876543210',
            'country' => 'India', 'city' => 'Ahmedabad', 'address' => '123 Supplier Road', 'state' => 'Gujarat', 'pincode' => '380004',
            'bank_name' => 'Example bank', 'account_holder_name' => 'Partner', 'account_number' => 'NEW-BANK', 'ifsc_code' => 'HDFC0001234'], $changes);
    }
}
