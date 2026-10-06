<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('trade_name');
            $table->string('legal_name');
            $table->string('tagline')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->string('website')->nullable();
            $table->string('website_url')->nullable();
            $table->string('instagram')->nullable();
            $table->string('instagram_handle')->nullable();
            $table->string('gstin')->nullable();
            $table->string('pan')->nullable();
            $table->string('cin')->nullable();
            $table->string('iec')->nullable();
            $table->string('msme')->nullable();
            $table->string('lut')->nullable();
            $table->string('jurisdiction_city')->nullable();
            $table->string('jurisdiction_state')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('logo_print_path')->nullable();
            $table->timestamps();
        });

        Schema::create('organisation_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // billing, shipping, branch
            $table->string('label')->nullable();
            $table->string('line1')->nullable();
            $table->string('line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('pincode')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('organisation_banks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('account_number')->nullable();
            $table->string('ifsc')->nullable();
            $table->string('branch')->nullable();
            $table->string('swift')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        $id = DB::table('organisations')->insertGetId([
            'trade_name' => 'MissPack',
            'legal_name' => 'MissPack India Pvt Ltd',
            'tagline' => 'Packed Perfect',
            'email' => 'misspackindia@gmail.com',
            'mobile' => '7041110823',
            'website' => 'www.themisspack.com',
            'website_url' => 'https://www.themisspack.com',
            'instagram' => 'https://www.instagram.com/themisspack/',
            'instagram_handle' => '@themisspack',
            'gstin' => '24AATCM8816E1Z5',
            'pan' => 'AATCM8816E',
            'jurisdiction_city' => 'Ahmedabad',
            'jurisdiction_state' => 'Gujarat',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organisation_addresses')->insert([
            'organisation_id' => $id,
            'kind' => 'billing',
            'label' => 'Registered office',
            'line1' => 'E-410, 4th Floor, City Centre, Near Idgah Circle, Prem Darwaja Road, Idgah',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'country' => 'India',
            'pincode' => '380016',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organisation_addresses')->insert([
            'organisation_id' => $id,
            'kind' => 'shipping',
            'label' => 'Dispatch',
            'line1' => 'E-410, 4th Floor, City Centre, Near Idgah Circle, Prem Darwaja Road, Idgah',
            'city' => 'Ahmedabad',
            'state' => 'Gujarat',
            'country' => 'India',
            'pincode' => '380016',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('organisation_banks')->insert([
            'organisation_id' => $id,
            'label' => 'HDFC Navrangpura',
            'bank_name' => 'HDFC BANK LTD',
            'account_holder' => 'MISSPACK INDIA PRIVATE LIMITED',
            'account_number' => '50200115168612',
            'ifsc' => 'HDFC0000006',
            'branch' => 'NAVRANGPURA',
            'swift' => 'HDFCINBBXXX',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_banks');
        Schema::dropIfExists('organisation_addresses');
        Schema::dropIfExists('organisations');
    }
};
