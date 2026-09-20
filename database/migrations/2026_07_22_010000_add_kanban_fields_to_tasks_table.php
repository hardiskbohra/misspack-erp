<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('tasks', 'category')) {
                $table->string('category')->nullable()->after('status');
            }

            if (! Schema::hasColumn('tasks', 'image_url')) {
                $table->string('image_url', 2048)->nullable()->after('category');
            }

            if (! Schema::hasColumn('tasks', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('image_url');
            }
        });

        DB::table('tasks')->where('status', 'pending')->update(['status' => 'new_request']);

        if (Schema::hasColumn('tasks', 'sort_order')) {
            $tasks = DB::table('tasks')->orderBy('due_date')->orderBy('id')->get(['id']);
            $order = 1;
            foreach ($tasks as $task) {
                DB::table('tasks')->where('id', $task->id)->update(['sort_order' => $order]);
                $order++;
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tasks')) {
            return;
        }

        DB::table('tasks')->where('status', 'new_request')->update(['status' => 'pending']);

        Schema::table('tasks', function (Blueprint $table) {
            $columns = [];

            foreach (['category', 'image_url', 'sort_order'] as $column) {
                if (Schema::hasColumn('tasks', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
