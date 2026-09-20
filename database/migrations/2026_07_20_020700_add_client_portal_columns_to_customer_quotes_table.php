<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_quotes')) {
            return;
        }

        Schema::table('customer_quotes', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_quotes', 'show_client_portal')) {
                $table->boolean('show_client_portal')->default(true)->after('status');
                $table->index('show_client_portal');
            }
            if (! Schema::hasColumn('customer_quotes', 'portal_published_at')) {
                $table->timestamp('portal_published_at')->nullable()->after('show_client_portal');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('customer_quotes')) {
            return;
        }

        Schema::table('customer_quotes', function (Blueprint $table) {
            if (Schema::hasColumn('customer_quotes', 'show_client_portal')) {
                $table->dropIndex(['show_client_portal']);
            }
        });

        Schema::table('customer_quotes', function (Blueprint $table) {
            $columns = [];
            foreach (['show_client_portal', 'portal_published_at'] as $column) {
                if (Schema::hasColumn('customer_quotes', $column)) {
                    $columns[] = $column;
                }
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
