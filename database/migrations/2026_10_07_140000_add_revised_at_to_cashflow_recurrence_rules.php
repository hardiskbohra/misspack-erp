<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a rule was changed **after the office answered it**.
 *
 * A rule used to be editable until it was approved and never again — approving
 * fixed the recipe, and a wrong amount was a rule to end and write again. The
 * office asked for the edit instead, and that is the right ask: ending a salary
 * rule to correct its figure loses the history of a run that is still running.
 *
 * So the intake edits any rule now, and the plan re-reads the dates nobody has
 * decided from whatever the recipe became (`RecurrencePlan::reconcile()`). What
 * an edit must not do is *hide* — the office said yes to a rule that read a
 * certain way, and a rule that has since been changed should say so on its own
 * record rather than quietly posting a different amount.
 *
 * `decided_at` cannot answer that question. It is stamped by the answer, and
 * every later save — pausing, resuming, ending, and the edit itself — moves
 * `updated_at` but not this. So the fact gets a column: `revised_at` is written
 * **only** by `RecurrenceIntake::update()`, and only when the rule had already
 * been answered and something in it actually changed. A fresh ask clears it, for
 * the same reason a fresh ask clears `decided_*`: the answer it refers to is no
 * longer the answer on the table.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* Re-runnable: a half-applied `migrate` on a slow connection is the one
           thing that produces this column without a record of it, and asking
           twice is cheap. */
        if (Schema::hasColumn('cashflow_recurrence_rules', 'revised_at')) {
            return;
        }

        Schema::table('cashflow_recurrence_rules', function (Blueprint $table) {
            $table->dateTime('revised_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cashflow_recurrence_rules', function (Blueprint $table) {
            $table->dropColumn('revised_at');
        });
    }
};
