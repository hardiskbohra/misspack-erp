<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The three quote modules are gone — customer quotes, vendor quotes and lead
 * quotes all left the office together, and nothing reads their tables any more.
 *
 * The six tables only exist on a database that predates the removal: their
 * create migrations are deleted, so a fresh install never builds them. Every
 * statement is guarded for exactly that reason. The two columns the old quote
 * forms once filled are dropped where they are still standing, and the
 * vendor-quote status list leaves the lead dropdown masters with the module.
 *
 * down() is deliberately empty: a dropped quote table cannot be rebuilt from
 * data the app still holds, and the module that owned it no longer exists.
 */
return new class extends Migration
{
    /** @var list<string> Children first, so a foreign key never blocks its parent. */
    private const TABLES = [
        'customer_quote_items',
        'customer_quotes',
        'vendor_quote_prices',
        'vendor_quotes',
        'lead_quote_items',
        'lead_quotes',
    ];

    /** @var array<string, list<string>> */
    private const COLUMNS = [
        'projects' => ['customer_quote_id'],
        'sales_invoices' => ['customer_quote_id'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                Schema::drop($table);
            }
        }

        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                    $blueprint->dropColumn($column);
                });
            }
        }

        if (Schema::hasTable('lead_master_options') && Schema::hasColumn('lead_master_options', 'group')) {
            DB::table('lead_master_options')->where('group', 'quote_status')->delete();
        }
    }

    public function down(): void
    {
        // Nothing to restore: the quote modules were removed, not parked, and an
        // empty shell of a table no page can read is worse than no table.
    }
};
