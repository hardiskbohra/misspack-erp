<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_quality_inspections', function (Blueprint $table) {
            $table->string('evidence_path')->nullable()->change();
            $table->string('evidence_original_name')->nullable()->change();
            $table->string('evidence_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('project_quality_inspections')->whereNull('evidence_path')->update([
            'evidence_path' => '',
            'evidence_original_name' => '',
            'evidence_hash' => str_repeat('0', 64),
        ]);
        Schema::table('project_quality_inspections', function (Blueprint $table) {
            $table->string('evidence_path')->nullable(false)->change();
            $table->string('evidence_original_name')->nullable(false)->change();
            $table->string('evidence_hash', 64)->nullable(false)->change();
        });
    }
};
