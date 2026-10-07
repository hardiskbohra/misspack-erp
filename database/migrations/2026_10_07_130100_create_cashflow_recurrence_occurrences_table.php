<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One occurrence: a date on which a rule has to be decided.
 *
 * The row is the **ask**, not the money. It carries the date and the state of
 * the decision; the amount lives on the rule until the occurrence is approved,
 * and after that on the ledger entry the approval posts. Nothing is copied onto
 * this row that can be read from the rule, for the same reason a rule carries
 * no counter: two copies of one fact drift, and the copy that drifts is always
 * the one nobody is looking at.
 *
 * `sequence` is the occurrence's number in its rule — 1 is the effective date,
 * 12 is the twelfth payment — and it is the row's identity inside the rule:
 * `unique(rule_id, sequence)` is what makes re-planning a reconciliation rather
 * than a rebuild. An occurrence that keeps its number keeps its row, its
 * notification and its history; one the new plan no longer contains is
 * *cancelled*, never deleted, so "we had promised a payment on the 5th and
 * withdrew it" stays readable.
 *
 * Statuses:
 *
 *   - `pending`   — planned. It may still be in the future, due today, or
 *                   overdue; which of those it is, is read from today's date,
 *                   never stored (a stored "overdue" is wrong by tomorrow);
 *   - `approved`  — decided yes, and `cashflow_entry_id` is the entry it posted;
 *   - `skipped`   — decided no, for this date only; the rule keeps running;
 *   - `cancelled` — withdrawn from the plan (the rule was paused, ended, or
 *                   re-planned onto other dates). Never money, never a decision.
 *
 * `notified_at` is when the office was told this one needs approval. It is one
 * timestamp rather than a count, because the notification is one fact: the ask
 * goes out once, on the effective date, and a second email about the same
 * payment the next morning is exactly what the office complained about.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_recurrence_occurrences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cashflow_recurrence_rule_id')
                ->constrained('cashflow_recurrence_rules')
                ->cascadeOnDelete();

            /* The occurrence's own number in its rule — and with the rule, its
               identity: a re-plan matches rows on it. */
            $table->unsignedInteger('sequence');
            $table->date('effective_date');

            $table->string('status', 20)->default('pending');

            /* The ledger entry an approval posted. Null on every row that has
               not been approved; the mirror is one-way, so nothing un-approves. */
            $table->foreignId('cashflow_entry_id')->nullable()->constrained('cashflow_entries')->nullOnDelete();

            $table->dateTime('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision_note', 255)->nullable();

            $table->dateTime('notified_at')->nullable();

            $table->timestamps();

            $table->unique(['cashflow_recurrence_rule_id', 'sequence']);

            /* The two questions the screens ask: "what is waiting for approval
               on this date / up to today" and "what is this rule's plan". */
            $table->index(['status', 'effective_date']);
            $table->index(['cashflow_recurrence_rule_id', 'effective_date']);
            $table->index('cashflow_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_recurrence_occurrences');
    }
};
