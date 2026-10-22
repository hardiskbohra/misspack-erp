<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('project_milestones');

        if (Schema::hasColumn('project_tracking_updates', 'progress_percent')) {
            Schema::table('project_tracking_updates', function (Blueprint $table) {
                $table->dropColumn('progress_percent');
            });
        }
        if (Schema::hasColumn('project_workflows', 'reported_progress')) {
            Schema::table('project_workflows', function (Blueprint $table) {
                $table->dropColumn('reported_progress');
            });
        }

        if (Schema::hasTable('project_workflows') && Schema::hasTable('projects')) {
            DB::table('projects')
                ->join('project_workflows', 'project_workflows.project_id', '=', 'projects.id')
                ->update(['projects.progress_percent' => DB::raw('project_workflows.calculated_progress')]);
        }
    }

    public function down(): void
    {
        // Milestone history and manual progress are intentionally retired and
        // cannot be reconstructed safely after this migration removes them.
    }
};
