<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Generated titles/URLs and formatted recipient lists can outgrow their individual inputs. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_alerts', function (Blueprint $table): void {
            $table->text('title')->change();
            $table->text('action_url')->nullable()->change();
        });
        Schema::table('client_portal_notifications', function (Blueprint $table): void {
            $table->text('title')->change();
            $table->text('action_url')->nullable()->change();
        });
        Schema::table('project_exceptions', function (Blueprint $table): void {
            $table->text('title')->change();
        });
        Schema::table('sales_invoice_sends', function (Blueprint $table): void {
            $table->text('recipients')->change();
            $table->text('cc')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Check *all* columns before any DDL: MySQL schema changes commit
        // implicitly, and SQLite does not enforce VARCHAR lengths at all.
        $length = DB::getDriverName() === 'sqlite' ? 'length' : 'char_length';
        foreach ([
            ['office_alerts', 'title', 255], ['office_alerts', 'action_url', 255],
            ['client_portal_notifications', 'title', 255], ['client_portal_notifications', 'action_url', 255],
            ['project_exceptions', 'title', 255],
            ['sales_invoice_sends', 'recipients', 500], ['sales_invoice_sends', 'cc', 500],
        ] as [$table, $column, $limit]) {
            if (DB::table($table)->whereRaw($length.'('.$column.') > ?', [$limit])->exists()) {
                throw new RuntimeException("Cannot roll back: {$table}.{$column} contains values longer than {$limit} characters.");
            }
        }

        Schema::table('office_alerts', function (Blueprint $table): void {
            $table->string('title')->change();
            $table->string('action_url')->nullable()->change();
        });
        Schema::table('client_portal_notifications', function (Blueprint $table): void {
            $table->string('title')->change();
            $table->string('action_url')->nullable()->change();
        });
        Schema::table('project_exceptions', function (Blueprint $table): void {
            $table->string('title')->change();
        });
        Schema::table('sales_invoice_sends', function (Blueprint $table): void {
            $table->string('recipients', 500)->change();
            $table->string('cc', 500)->nullable()->change();
        });
    }
};
