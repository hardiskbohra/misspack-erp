<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Project payment entries are gone. They were a second copy of money the
 * cashflow ledger already holds: a row recorded on the project, with its own
 * publish flag for the client portal, next to the ledger entry that carried
 * the same receipt. Two rows for one payment is two facts to keep in step, so
 * the ledger is the only source of truth for project-level money now — a
 * project receipt is a ledger entry tagged to the project (`project_id`),
 * money in, booked or reconciled, read through `App\Services\ProjectReceipts`.
 *
 * The table only exists on a database that predates the removal: its create
 * migration is deleted, so a fresh install never builds it. Everything is
 * guarded for exactly that reason. `project_attachments.project_payment_id`
 * goes with it — it existed to hang a payment proof off a payment row; a proof
 * is attached to the project like every other document.
 *
 * down() is deliberately empty: a dropped ledger-copy cannot be rebuilt from
 * data the app still holds, and no screen reads it any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_payments')) {
            Schema::drop('project_payments');
        }

        if (Schema::hasTable('project_attachments') && Schema::hasColumn('project_attachments', 'project_payment_id')) {
            Schema::table('project_attachments', function (Blueprint $table): void {
                $table->dropColumn('project_payment_id');
            });
        }
    }

    public function down(): void
    {
        // Nothing to restore: the ledger holds the money, and the module that
        // wrote these rows no longer exists.
    }
};
