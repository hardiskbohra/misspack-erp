<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cashflow_master_options')) {
            DB::table('cashflow_master_options')
                ->where('group', 'currency')
                ->orderBy('id')
                ->get()
                ->each(function ($option) {
                    $oldKey = (string) $option->key;
                    $newKey = strtoupper(trim($oldKey));

                    if ($newKey === $oldKey) {
                        return;
                    }

                    // Update any account/entry rows using the old lowercase currency key.
                    if (Schema::hasTable('cashflow_accounts')) {
                        DB::table('cashflow_accounts')->where('currency', $oldKey)->update(['currency' => $newKey]);
                    }

                    if (Schema::hasTable('cashflow_entries')) {
                        DB::table('cashflow_entries')->where('currency', $oldKey)->update(['currency' => $newKey]);
                    }

                    $existing = DB::table('cashflow_master_options')
                        ->where('group', 'currency')
                        ->where('key', $newKey)
                        ->where('id', '!=', $option->id)
                        ->first();

                    if ($existing) {
                        DB::table('cashflow_master_options')->where('id', $option->id)->delete();
                        return;
                    }

                    DB::table('cashflow_master_options')
                        ->where('id', $option->id)
                        ->update([
                            'key' => $newKey,
                            'updated_at' => now(),
                        ]);
                });
        }
    }

    public function down(): void
    {
        // No-op: currency codes should remain uppercase.
    }
};
