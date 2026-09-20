<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'portal_enabled')) {
                $table->boolean('portal_enabled')->default(false)->after('public_token');
            }
            if (! Schema::hasColumn('clients', 'portal_enabled_at')) {
                $table->timestamp('portal_enabled_at')->nullable()->after('portal_enabled');
            }
            if (! Schema::hasColumn('clients', 'portal_last_shared_at')) {
                $table->timestamp('portal_last_shared_at')->nullable()->after('portal_enabled_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('clients')) {
            return;
        }

        Schema::table('clients', function (Blueprint $table) {
            $columns = [];
            foreach (['portal_enabled', 'portal_enabled_at', 'portal_last_shared_at'] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
