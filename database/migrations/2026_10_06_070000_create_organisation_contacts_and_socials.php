<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('department');
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('email')->nullable();
            $table->string('mobile')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        Schema::create('organisation_socials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('network');
            $table->string('handle')->nullable();
            $table->string('url');
            $table->timestamps();
        });

        $org = DB::table('organisations')->first();
        if ($org) {
            if (filled($org->email) || filled($org->mobile)) {
                DB::table('organisation_contacts')->insert([
                    'organisation_id' => $org->id,
                    'department' => 'office',
                    'name' => $org->legal_name ?: $org->trade_name,
                    'designation' => 'Office',
                    'email' => $org->email,
                    'mobile' => $org->mobile,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if (filled($org->instagram) || filled($org->instagram_handle)) {
                DB::table('organisation_socials')->insert([
                    'organisation_id' => $org->id,
                    'network' => 'instagram',
                    'handle' => $org->instagram_handle,
                    'url' => $org->instagram ?: 'https://www.instagram.com/themisspack/',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_socials');
        Schema::dropIfExists('organisation_contacts');
    }
};
