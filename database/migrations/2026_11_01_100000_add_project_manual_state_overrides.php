<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('manual_status', 40)->nullable();
            $table->unsignedTinyInteger('manual_progress_percent')->nullable();
            // A legacy project may not yet have a workflow from which to restore
            // its calculated value when the administrator clears an override.
            $table->string('manual_status_previous', 40)->nullable();
            $table->unsignedTinyInteger('manual_progress_previous')->nullable();
        });
    }

    public function down(): void
    {
        // A downgrade must not silently discard active decisions, including on
        // soft-deleted projects. Reopen/clear them first or restore a backup.
        $active = DB::table('projects')->where(function ($query) {
            $query->whereNotNull('manual_status')->orWhereNotNull('manual_progress_percent')
                ->orWhere('status', 'manually_closed');
        })->exists();
        if ($active) {
            throw new RuntimeException('Cannot remove project override columns while manual state or manual closures are active.');
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['manual_status', 'manual_progress_percent', 'manual_status_previous', 'manual_progress_previous']);
        });
    }
};
