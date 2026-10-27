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
            if (DB::getDriverName() === 'sqlite') {
                // Laravel's SQLite joined-update grammar moves the join into a
                // WHERE subquery, where it is no longer visible to SET. Use a
                // correlated value and leave projects without a workflow alone.
                DB::table('projects')
                    ->whereExists(fn ($query) => $query->selectRaw('1')->from('project_workflows')
                        ->whereColumn('project_workflows.project_id', 'projects.id'))
                    ->update(['progress_percent' => DB::raw('(select calculated_progress from project_workflows where project_workflows.project_id = projects.id)')]);
            } else {
                DB::table('projects')
                    ->join('project_workflows', 'project_workflows.project_id', '=', 'projects.id')
                    ->update(['projects.progress_percent' => DB::raw('project_workflows.calculated_progress')]);
            }
        }
    }

    public function down(): void
    {
        // Milestone history and manual progress are intentionally retired and
        // cannot be reconstructed safely after this migration removes them.
    }
};
