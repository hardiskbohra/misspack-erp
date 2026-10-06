<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The vendor file's numbers, moved onto one series: MP-VEN-001, MP-VEN-002.
 *
 * Vendors created before the series existed carry the date-stamped form the
 * controller used to generate (`VEN-261004-0001`). Nothing references the number
 * except the vendor's own screens and the statement's party code, so the old
 * numbers are rewritten in creation order — the oldest vendor keeps position
 * one and every later record follows it.
 *
 * Only rows that do not already read as `MP-VEN-nnn` are touched, so running
 * this against a database that has already adopted the series changes nothing.
 * The down() method is deliberately a no-op: the old date-stamped numbers carry
 * no meaning the file still uses.
 */
return new class extends Migration
{
    private const PREFIX = 'MP-VEN-';

    public function up(): void
    {
        if (! Schema::hasTable('vendors')) {
            return;
        }

        /* Never step on a number the file already handed out: the series
           continues from the highest one on the table. */
        $next = 1;

        DB::table('vendors')
            ->where('vendor_number', 'like', self::PREFIX.'%')
            ->orderBy('vendor_number')
            ->pluck('vendor_number')
            ->each(function ($number) use (&$next) {
                $digits = (int) preg_replace('/\D/', '', substr((string) $number, strlen(self::PREFIX)));
                $next = max($next, $digits + 1);
            });

        DB::table('vendors')
            ->orderBy('id')
            ->select('id', 'vendor_number')
            ->chunkById(200, function ($vendors) use (&$next) {
                foreach ($vendors as $vendor) {
                    $current = (string) $vendor->vendor_number;

                    if (preg_match('/^'.preg_quote(self::PREFIX, '/').'\d+$/', $current)) {
                        continue;
                    }

                    $candidate = self::PREFIX.str_pad((string) $next, 3, '0', STR_PAD_LEFT);

                    /* The number is unique on the table; skip anything taken. */
                    while (DB::table('vendors')->where('vendor_number', $candidate)->exists()) {
                        $next++;
                        $candidate = self::PREFIX.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
                    }

                    DB::table('vendors')->where('id', $vendor->id)->update(['vendor_number' => $candidate]);
                    $next++;
                }
            });
    }

    public function down(): void
    {
        // The previous numbers carried the date they were generated; nothing
        // in the file can reconstruct them, and nothing reads them.
    }
};
