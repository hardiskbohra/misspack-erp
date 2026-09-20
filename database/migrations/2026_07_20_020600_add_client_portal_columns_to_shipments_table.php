<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            if (! Schema::hasColumn('shipments', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->after('id');
                $table->index('client_id');
            }
            if (! Schema::hasColumn('shipments', 'show_client_portal')) {
                $table->boolean('show_client_portal')->default(false)->after('client_id');
                $table->index('show_client_portal');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shipments')) {
            return;
        }

        Schema::table('shipments', function (Blueprint $table) {
            if (Schema::hasColumn('shipments', 'client_id')) {
                $table->dropIndex(['client_id']);
            }
            if (Schema::hasColumn('shipments', 'show_client_portal')) {
                $table->dropIndex(['show_client_portal']);
            }
        });

        Schema::table('shipments', function (Blueprint $table) {
            $columns = [];
            foreach (['client_id', 'show_client_portal'] as $column) {
                if (Schema::hasColumn('shipments', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
