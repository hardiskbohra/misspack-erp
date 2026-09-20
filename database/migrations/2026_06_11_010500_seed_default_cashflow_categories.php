<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cashflow_categories')) {
            return;
        }

        $now = now();

        $categories = [
            ['Sales Receipt', 'income', '#10b981'],
            ['Client Receipt', 'income', '#10b981'],
            ['Interest Income', 'income', '#10b981'],
            ['Other Income', 'income', '#10b981'],

            ['Vendor Payment', 'expense', '#ef4770'],
            ['Purchase Payment', 'expense', '#ef4770'],
            ['Freight / Logistics', 'expense', '#ef4770'],
            ['Office Expense', 'expense', '#ef4770'],
            ['Salary / Wages', 'expense', '#ef4770'],
            ['Cash Expense', 'expense', '#ef4770'],
            ['Other Expense', 'expense', '#ef4770'],

            ['GST Payment', 'tax', '#f59e0b'],
            ['TDS Payment', 'tax', '#f59e0b'],
            ['Income Tax', 'tax', '#f59e0b'],

            ['Bank Charges', 'bank_charge', '#8b5cf6'],
            ['Loan Interest', 'bank_charge', '#8b5cf6'],

            ['Internal Transfer', 'transfer', '#4f83f1'],
            ['Owner Capital', 'transfer', '#4f83f1'],
            ['Contra Entry', 'transfer', '#4f83f1'],
        ];

        foreach ($categories as [$name, $type, $color]) {
            $exists = DB::table('cashflow_categories')
                ->where('name', $name)
                ->where('type', $type)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('cashflow_categories')->insert([
                'name' => $name,
                'type' => $type,
                'color' => $color,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Keep categories because entries may already be linked with them.
    }
};
