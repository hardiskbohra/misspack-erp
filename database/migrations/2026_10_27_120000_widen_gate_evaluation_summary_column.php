<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        Schema::table('project_gate_evaluations', function (Blueprint $table) {
            $table->string('summary')->change();
        });
    }
};
