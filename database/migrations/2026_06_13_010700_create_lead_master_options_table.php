<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_master_options', function (Blueprint $table) {
            $table->id();
            $table->string('group', 60); // lead_status, lead_source, lead_priority, finish, printing, currency, quote_status, incoterm
            $table->string('key', 100);
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
            'lead_status' => [
                ['new', 'New', '#4f83f1'],
                ['requirement_received', 'Requirement Received', '#687386'],
                ['sourcing', 'Sourcing', '#8b5cf6'],
                ['quoted', 'Quoted', '#f59e0b'],
                ['negotiation', 'Negotiation', '#d97706'],
                ['won', 'Won', '#10b981'],
                ['lost', 'Lost', '#ef4770'],
                ['on_hold', 'On Hold', '#6b7280'],
            ],
            'lead_source' => [
                ['whatsapp', 'WhatsApp', '#10b981'],
                ['email', 'Email', '#4f83f1'],
                ['website', 'Website', '#159ff7'],
                ['call', 'Call', '#f59e0b'],
                ['referral', 'Referral', '#8b5cf6'],
                ['exhibition', 'Exhibition', '#ef4770'],
                ['other', 'Other', '#687386'],
            ],
            'lead_priority' => [
                ['low', 'Low', '#10b981'],
                ['medium', 'Medium', '#4f83f1'],
                ['high', 'High', '#f59e0b'],
                ['urgent', 'Urgent', '#ef4770'],
            ],
            'finish' => [
                ['matte', 'Matte', '#687386'],
                ['glossy', 'Glossy', '#4f83f1'],
                ['both', 'Matte + Glossy', '#8b5cf6'],
                ['any', 'Any', '#10b981'],
                ['custom', 'Custom', '#f59e0b'],
            ],
            'printing' => [
                ['none', 'No Printing', '#687386'],
                ['one_color', 'One Color Printing', '#4f83f1'],
                ['multi_color', 'Multi Color Printing', '#8b5cf6'],
                ['label', 'Label', '#10b981'],
                ['embossing', 'Embossing', '#f59e0b'],
                ['custom', 'Custom', '#ef4770'],
            ],
            'currency' => [
                ['INR', 'INR', '#4f83f1'],
                ['USD', 'USD', '#10b981'],
                ['RMB', 'RMB', '#f59e0b'],
            ],
            'quote_status' => [
                ['requested', 'Requested', '#4f83f1'],
                ['received', 'Received', '#f59e0b'],
                ['shortlisted', 'Shortlisted', '#8b5cf6'],
                ['rejected', 'Rejected', '#ef4770'],
                ['approved', 'Approved', '#10b981'],
                ['converted', 'Converted', '#12cbb7'],
            ],
            'incoterm' => [
                ['EXW', 'EXW', '#687386'],
                ['FOB', 'FOB', '#4f83f1'],
                ['CIF', 'CIF', '#8b5cf6'],
                ['DDP', 'DDP', '#10b981'],
                ['DAP', 'DAP', '#f59e0b'],
            ],
            'capacity_unit' => [
                ['ml', 'ml', '#4f83f1'],
                ['gm', 'gm', '#10b981'],
                ['oz', 'oz', '#8b5cf6'],
                ['pcs', 'pcs', '#f59e0b'],
                ['kg', 'kg', '#ef4770'],
            ],
        ];

        foreach ($defaults as $group => $options) {
            foreach ($options as $index => $option) {
                $rows[] = [
                    'group' => $group,
                    'key' => $option[0],
                    'label' => $option[1],
                    'color' => $option[2],
                    'sort_order' => ($index + 1) * 10,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::table('lead_master_options')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_master_options');
    }
};
