<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two columns the vendor module needed to become a payable book rather
 * than a contact list:
 *
 * - `vendor_payment_entries.due_date` — when a bill falls due. Without it the
 *   office can see what it owes but not what is late, which is the only
 *   question a supplier ever asks on the phone.
 * - `vendor_contacts` — the other people at the same supplier. The vendor row
 *   already holds the primary contact; this is the accountant, the dispatch
 *   clerk and the quality inspector behind them.
 *
 * Both are guarded, so a database that already has them (or was restored from
 * a later backup) migrates without error.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vendor_payment_entries') && ! Schema::hasColumn('vendor_payment_entries', 'due_date')) {
            Schema::table('vendor_payment_entries', function (Blueprint $table) {
                $table->date('due_date')->nullable()->after('transaction_date');
            });
        }

        if (Schema::hasTable('vendor_payment_entries') && ! $this->hasIndex('vendor_payment_entries', 'vendor_payment_entries_vendor_id_due_date_index')) {
            Schema::table('vendor_payment_entries', function (Blueprint $table) {
                $table->index(['vendor_id', 'due_date'], 'vendor_payment_entries_vendor_id_due_date_index');
            });
        }

        if (! Schema::hasTable('vendor_contacts')) {
            Schema::create('vendor_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->string('name');
                $table->string('designation')->nullable();
                $table->string('email')->nullable();
                $table->string('mobile', 40)->nullable();
                $table->string('whatsapp', 40)->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['vendor_id', 'name']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_contacts');

        if (Schema::hasTable('vendor_payment_entries') && $this->hasIndex('vendor_payment_entries', 'vendor_payment_entries_vendor_id_due_date_index')) {
            Schema::table('vendor_payment_entries', function (Blueprint $table) {
                $table->dropIndex('vendor_payment_entries_vendor_id_due_date_index');
            });
        }

        if (Schema::hasTable('vendor_payment_entries') && Schema::hasColumn('vendor_payment_entries', 'due_date')) {
            Schema::table('vendor_payment_entries', function (Blueprint $table) {
                $table->dropColumn('due_date');
            });
        }
    }

    /**
     * Laravel has no index introspection on the schema builder, so the index is
     * looked up through the connection's own schema manager.
     */
    private function hasIndex(string $table, string $index): bool
    {
        try {
            $indexes = Schema::getConnection()->getSchemaBuilder()->getIndexes($table);
        } catch (\Throwable) {
            return false;
        }

        foreach ($indexes as $existing) {
            if (($existing['name'] ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
