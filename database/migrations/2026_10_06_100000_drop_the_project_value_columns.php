<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The project's value and its budget are the documents'.
 *
 * `estimated_value` was typed on the project form ("Estimated Deal Value") and
 * `budget_amount` beside it ("Project Budget"), while the invoices and purchase
 * documents that decide both were raised elsewhere — so a project could claim a
 * value no invoice agreed with, and the budget figure nothing else read was
 * wrong from the day it was typed. Both are derived now:
 * `Project::estimatedValue()` sums the tax invoices and the proformas no tax
 * invoice has carried, and `Project::budgetAmount()` sums the purchase orders
 * and bills. The columns have no writer left, and a stored copy of a derived
 * fact is how the two start disagreeing.
 *
 * Guarded, and only on a database that predates the change: the create
 * migration is edited to stop making the columns, so a fresh install never has
 * them. `down()` is empty — a dropped figure cannot be rebuilt from data the
 * app no longer keeps, and nothing reads it any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('projects')) {
            return;
        }

        $columns = array_values(array_filter(
            ['estimated_value', 'budget_amount'],
            fn (string $column) => Schema::hasColumn('projects', $column)
        ));

        if ($columns !== []) {
            Schema::table('projects', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        // Nothing to restore: the documents hold both figures now.
    }
};
