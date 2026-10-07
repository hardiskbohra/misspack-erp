<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A standing instruction to pay or receive the same money again: salary, rent,
 * an internet bill, a monthly supplier, a quarterly advance.
 *
 * The row is a **rule, not a payment**. Nothing here is money that has moved —
 * the payments are the occurrence rows, and the ledger entries they post when
 * the office approves them. That separation is the whole design: a rule can be
 * drafted, argued about, corrected and thrown away without a single rupee ever
 * reaching `cashflow_entries`.
 *
 * The schedule is four columns and no fifth place to keep it true:
 *
 *   - `frequency` — daily, weekly, monthly, quarterly, half_yearly, yearly;
 *   - `starts_on` — the **effective date**: the day of the first occurrence, and
 *     the day-of-month / day-of-year every later one keeps its anchor on (a
 *     rule that starts on the 31st pays on the 31st, and on the 28th in
 *     February, because a month has no 31st to pay on);
 *   - `ends_on` and `occurrence_limit` — the two ways the window closes, either
 *     of which may be left empty. Both empty means "until we say stop".
 *
 * `status` walks `draft → active ⇄ paused → ended`. A rule is written as a
 * **draft** and does nothing at all until it is approved: drafts are not
 * planned, do not notify and cannot post. The office's decisions are kept as
 * facts — who asked (`requested_*`), who decided and what they said
 * (`decided_*`, `decision_note`) — because "who agreed to this standing
 * payment" is asked months later, and a status column alone cannot answer it.
 *
 * There is deliberately **no counter of released occurrences, no stored next
 * date and no stored amount-paid-to-date**: the occurrences are the log, and a
 * count beside a log is a second answer that drifts. `CashflowRecurrenceRule`
 * derives every one of those from the rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_recurrence_rules', function (Blueprint $table) {
            $table->id();

            /* What the office calls it, and what the entry will be titled.
               "Office rent — Shivalik Complex" is a rule; "Rent" is a note. */
            $table->string('title', 160);
            $table->string('particular', 160);

            /* The money, in the same vocabulary the ledger uses: a direction,
               one amount, a currency, and the account it leaves from. */
            $table->string('transaction_type', 10)->default('debit');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 8)->default('INR');

            /* A null account is a rule whose account was removed: the form
               asks for one and the plan refuses to post without one, but the
               rule itself survives so its history does. */
            $table->foreignId('account_id')->nullable()->constrained('cashflow_accounts')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('cashflow_categories')->nullOnDelete();
            $table->string('payment_mode', 20)->nullable();
            $table->string('expense_head', 120)->nullable();

            /* Whose money it is, in the ledger's own party vocabulary: a linked
               client / vendor / employee / office service, or a name typed in. */
            $table->string('related_party_type', 30)->default('other');
            $table->string('related_party_name', 160)->nullable();
            $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            $table->foreignId('vendor_id')->nullable()->constrained('vendors')->nullOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('office_service_id')->nullable()->constrained('office_services')->nullOnDelete();

            $table->text('notes')->nullable();

            /* The schedule — the four columns above. */
            $table->string('frequency', 20);
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->unsignedSmallInteger('occurrence_limit')->nullable();

            /* The state and the two decisions that move it. */
            $table->string('status', 20)->default('draft');
            $table->dateTime('requested_at')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('decision_note')->nullable();
            $table->dateTime('paused_at')->nullable();
            $table->dateTime('ended_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            /* The questions the list is asked: what is waiting on approval,
               what runs today, what this account costs us. */
            $table->index(['status', 'starts_on']);
            $table->index(['frequency', 'status']);
            $table->index('account_id');
            $table->index('starts_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_recurrence_rules');
    }
};
