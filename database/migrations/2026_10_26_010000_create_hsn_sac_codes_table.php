<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /* The HSN/SAC rate master. One row per code the business actually uses:
           invoice lines keep their own copy of the rate (tax documents freeze
           what was charged), this table is the lookup the forms offer and the
           e-invoice payload checks against. Rates follow GST notifications, so
           the seeder below is a starter set for a packaging manufacturer —
           each row is editable in Settings → Taxes & HSN. */
        Schema::create('hsn_sac_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 8)->unique();          // 4, 6 or 8 digits; 4-5 for goods, 4-6+ for services
            $table->string('kind', 10)->default('goods'); // goods | service (SAC)
            $table->string('description');
            $table->decimal('gst_rate', 5, 2);            // total GST % — CGST+SGST split is derived at invoice time
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        $now = now();
        $seed = [
            // ---- goods a packaging house sells --------------------------------
            ['3923', 'goods', 'Articles for the conveyance or packing of goods, of plastics (boxes, cases, crates)', 18.00],
            ['3920', 'goods', 'Plates, sheets, film, foil and strip of plastics, non-cellular', 18.00],
            ['4819', 'goods', 'Cartons, boxes, cases, bags and other packing containers, of paper', 18.00],
            ['4823', 'goods', 'Other paper, paperboard and articles cut to size or shape', 18.00],
            ['7607', 'goods', 'Aluminium foil, not backed (packaging foil)', 18.00],
            ['3921', 'goods', 'Other plates, sheets, film, foil and strip of plastics', 18.00],
            ['4821', 'goods', 'Paper or paperboard labels of all kinds, printed or not', 18.00],
            ['8443', 'goods', 'Printing machinery and parts used for printing', 18.00],
            // ---- services a packaging house bills -----------------------------
            ['9989', 'service', 'Other manufacturing services (job work / packing services on materials owned by others)', 18.00],
            ['9988', 'service', 'Manufacturing services on physical inputs owned by others (job work)', 18.00],
            ['9997', 'service', 'Other services (packaging support, handling)', 18.00],
            ['9968', 'service', 'Postal and courier services', 18.00],
        ];

        foreach ($seed as $order => [$code, $kind, $description, $rate]) {
            DB::table('hsn_sac_codes')->insert([
                'code' => $code,
                'kind' => $kind,
                'description' => $description,
                'gst_rate' => $rate,
                'is_active' => true,
                'sort_order' => ($order + 1) * 10,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hsn_sac_codes');
    }
};
