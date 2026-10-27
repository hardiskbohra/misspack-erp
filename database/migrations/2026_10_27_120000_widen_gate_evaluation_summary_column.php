<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* A gate summary is prose, not a label: the dispatch gate spells every
   blocking issue into one human-readable message, which outgrew
   varchar(255) on MySQL strict mode and 500'd the whole shipment save.
   Text holds a real explanation without clipping the evidence. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_gate_evaluations', function (Blueprint $table) {
            $table->text('summary')->change();
        });
    }

    public function down(): void
    {
        $length = DB::getDriverName() === 'sqlite' ? 'length' : 'char_length';
        if (DB::table('project_gate_evaluations')->whereRaw($length.'(summary) > ?', [255])->exists()) {
            throw new RuntimeException('Cannot roll back: project_gate_evaluations.summary contains values longer than 255 characters.');
        }

        Schema::table('project_gate_evaluations', function (Blueprint $table) {
            $table->string('summary')->change();
        });
    }
};
