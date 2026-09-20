<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashflow_master_options', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60); // currency, accounting_status, payment_mode, related_party_type, account_type, category_type
            $table->string('key', 80);
            $table->string('label');
            $table->string('color', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['group', 'key']);
            $table->index(['group', 'is_active', 'sort_order']);
        });

        $now = now();
        $rows = [];
        $defaults = [
            'currency' => [
                ['INR', 'INR', '#4f83f1'],
                ['USD', 'USD', '#10b981'],
                ['RMB', 'RMB', '#f59e0b'],
            ],
            'accounting_status' => [
                ['pending', 'Pending', '#f59e0b'],
                ['booked', 'Booked', '#4f83f1'],
                ['reconciled', 'Reconciled', '#10b981'],
                ['disputed', 'Disputed', '#ef4770'],
                ['ignored', 'Ignored', '#687386'],
            ],
            'payment_mode' => [
                ['neft', 'NEFT', '#4f83f1'],
                ['rtgs', 'RTGS', '#4f83f1'],
                ['imps', 'IMPS', '#4f83f1'],
                ['upi', 'UPI', '#10b981'],
                ['cash', 'Cash', '#f59e0b'],
                ['cheque', 'Cheque', '#8b5cf6'],
                ['card', 'Card', '#159ff7'],
                ['other', 'Other', '#687386'],
            ],
            'related_party_type' => [
                ['client', 'Client', '#4f83f1'],
                ['vendor', 'Vendor', '#8b5cf6'],
                ['expense', 'Cash Expense', '#ef4770'],
                ['owner', 'Owner / Capital', '#10b981'],
                ['employee', 'Employee', '#f59e0b'],
                ['other', 'Other', '#687386'],
            ],
            'account_type' => [
                ['current', 'Current Account', '#4f83f1'],
                ['saving', 'Saving Account', '#10b981'],
                ['cash', 'Cash Account', '#f59e0b'],
                ['credit_card', 'Credit Card', '#8b5cf6'],
                ['loan', 'Loan Account', '#ef4770'],
            ],
            'category_type' => [
                ['income', 'Income', '#10b981'],
                ['expense', 'Expense', '#ef4770'],
                ['transfer', 'Transfer', '#4f83f1'],
                ['tax', 'Tax', '#f59e0b'],
                ['bank_charge', 'Bank Charge', '#8b5cf6'],
                ['other', 'Other', '#687386'],
            ],
        ];

        foreach ($defaults as $group => $options) {
            foreach ($options as $index => [$key, $label, $color]) {
                $rows[] = [
                    'group' => $group,
                    'key' => $key,
                    'label' => $label,
                    'color' => $color,
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('cashflow_master_options')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('cashflow_master_options');
    }
};
