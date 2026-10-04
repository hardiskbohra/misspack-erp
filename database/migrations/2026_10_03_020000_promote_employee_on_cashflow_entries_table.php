<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An employee on a cashflow entry stops being a name typed into a box.
 *
 * "Paid to Ramesh" is a string nobody can group by, filter on, or check: two
 * spellings are two people, and a report that says "employee-wise" while
 * grouping free text is a report that lies. Users already exist and already
 * carry a department and a designation, so the entry gets a real link.
 *
 * The old text is kept, not thrown away: it is what the entry actually said.
 * Every row that named an employee is matched to a user by exact name (the only
 * honest guess available), and anything that does not match keeps its name and
 * simply has no link — visible in the report as "not set", where somebody can
 * fix it in a moment rather than have the number quietly change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cashflow_entries')) {
            return;
        }

        if (! Schema::hasColumn('cashflow_entries', 'employee_id')) {
            Schema::table('cashflow_entries', function (Blueprint $table) {
                /* Beside the other party links, because that is what it is. */
                $table->foreignId('employee_id')->nullable()->after('vendor_id')
                    ->constrained('users')->nullOnDelete();
            });
        }

        $this->backfill();
    }

    /**
     * Match the name on the entry to a user, exactly. Case and surrounding
     * spaces are ignored (a database collation would do that anyway); anything
     * fuzzier would be inventing a link, and a wrong employee on a payment is
     * worse than none.
     */
    private function backfill(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('cashflow_entries', 'employee_id')) {
            return;
        }

        $byName = [];
        foreach (DB::table('users')->select('id', 'name')->orderBy('id')->get() as $user) {
            $key = mb_strtolower(trim((string) $user->name));
            if ($key !== '' && ! isset($byName[$key])) {
                $byName[$key] = (int) $user->id;
            }
        }

        if ($byName === []) {
            return;
        }

        DB::table('cashflow_entries')
            ->where('related_party_type', 'employee')
            ->whereNull('employee_id')
            ->whereNotNull('related_party_name')
            ->select('id', 'related_party_name')
            ->chunkById(500, function ($entries) use ($byName) {
                foreach ($entries as $entry) {
                    $key = mb_strtolower(trim((string) $entry->related_party_name));

                    if (isset($byName[$key])) {
                        DB::table('cashflow_entries')->where('id', $entry->id)
                            ->update(['employee_id' => $byName[$key]]);
                    }
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('cashflow_entries', 'employee_id')) {
            Schema::table('cashflow_entries', function (Blueprint $table) {
                $table->dropConstrainedForeignId('employee_id');
            });
        }
    }
};
