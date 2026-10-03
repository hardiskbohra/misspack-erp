<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A bill does not always have a bank line behind it.
 *
 * A purchase bill booked to a project, a document that arrives before the
 * payment does, an invoice settled outside this ledger, a receipt that belongs
 * to a month rather than to an entry — those files still belong in the month's
 * paperwork drawer. So the link to an entry becomes optional, and the document
 * carries its own party, date, amount and currency until an entry is matched to
 * it (or never, which is a valid answer).
 */
return new class extends Migration
{
    public function up(): void
    {
        /* the foreign key has to come off before the column can be rebuilt, and
           go back on after — a nullable column that still cascades with its
           entry when it has one */
        Schema::table('cashflow_attachments', function (Blueprint $table) {
            $table->dropForeign(['cashflow_entry_id']);
        });

        Schema::table('cashflow_attachments', function (Blueprint $table) {
            $table->foreignId('cashflow_entry_id')->nullable()->change();
        });

        Schema::table('cashflow_attachments', function (Blueprint $table) {
            $table->string('party_name')->nullable();
            $table->date('document_date')->nullable();
            $table->decimal('amount', 16, 2)->nullable();
            $table->string('currency', 3)->default('INR');
        });

        Schema::table('cashflow_attachments', function (Blueprint $table) {
            $table->foreign('cashflow_entry_id')->references('id')->on('cashflow_entries')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        /* a stand-alone document has nothing to hang on once the column is
           required again: rolling back drops those rows rather than losing the
           whole table */
        DB::table('cashflow_attachments')->whereNull('cashflow_entry_id')->delete();

        Schema::table('cashflow_attachments', function (Blueprint $table) {
            $table->dropForeign(['cashflow_entry_id']);
        });

        Schema::table('cashflow_attachments', function (Blueprint $table) {
            $table->dropColumn(['party_name', 'document_date', 'amount', 'currency']);
        });

        Schema::table('cashflow_attachments', function (Blueprint $table) {
            $table->foreignId('cashflow_entry_id')->nullable(false)->change();
        });

        Schema::table('cashflow_attachments', function (Blueprint $table) {
            $table->foreign('cashflow_entry_id')->references('id')->on('cashflow_entries')->cascadeOnDelete();
        });
    }
};
