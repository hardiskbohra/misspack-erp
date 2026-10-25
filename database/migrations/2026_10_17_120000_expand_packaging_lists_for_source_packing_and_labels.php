<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packaging_lists', function (Blueprint $table) {
            $table->string('source_type', 30)->nullable()->after('status')->index();
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type')->index();
            $table->foreignId('purchase_invoice_id')->nullable()->after('sales_invoice_id')->constrained('purchase_invoices')->nullOnDelete();
            $table->foreignId('packing_owner_id')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->boolean('auto_generated')->default(false)->after('packing_owner_id');
            $table->json('packing_warnings')->nullable()->after('auto_generated');
        });

        Schema::table('packaging_list_cartons', function (Blueprint $table) {
            $table->string('packing_mode', 20)->default('grouped')->after('carton_label');
            $table->string('carton_number')->nullable()->after('packing_mode');
            $table->string('carton_type', 40)->default('box')->after('carton_number');
            $table->string('barcode', 100)->nullable()->unique()->after('carton_type');
            $table->string('public_token', 64)->nullable()->unique()->after('barcode');
            $table->string('shipping_mark')->nullable()->after('public_token');
            $table->string('material')->nullable()->after('shipping_mark');
            $table->string('pallet_number')->nullable()->after('material');
            $table->string('seal_number')->nullable()->after('pallet_number');
            $table->decimal('tare_weight', 12, 3)->default(0)->after('gross_weight_per_carton');
            $table->boolean('is_fragile')->default(false)->after('tare_weight');
            $table->boolean('keep_dry')->default(false)->after('is_fragile');
            $table->boolean('this_side_up')->default(false)->after('keep_dry');
            $table->boolean('is_stackable')->default(true)->after('this_side_up');
            $table->boolean('is_hazardous')->default(false)->after('is_stackable');
            $table->text('handling_instructions')->nullable()->after('is_hazardous');
        });

        Schema::table('packaging_list_items', function (Blueprint $table) {
            $table->string('source_type', 30)->nullable()->after('product_id')->index();
            $table->unsignedBigInteger('source_item_id')->nullable()->after('source_type')->index();
            $table->decimal('source_quantity', 14, 3)->nullable()->after('source_item_id');
            $table->decimal('previously_packed_quantity', 14, 3)->default(0)->after('source_quantity');
            $table->string('sku')->nullable()->after('item_description');
            $table->string('customer_item_code')->nullable()->after('sku');
            $table->string('hs_code', 40)->nullable()->after('customer_item_code');
            $table->string('batch_number')->nullable()->after('hs_code');
            $table->json('serial_numbers')->nullable()->after('batch_number');
            $table->date('manufactured_at')->nullable()->after('serial_numbers');
            $table->date('expires_at')->nullable()->after('manufactured_at');
            $table->string('country_of_origin')->nullable()->after('expires_at');
            $table->decimal('unit_net_weight', 12, 3)->nullable()->after('country_of_origin');
            $table->decimal('unit_gross_weight', 12, 3)->nullable()->after('unit_net_weight');
            $table->text('packing_instructions')->nullable()->after('unit_gross_weight');
            $table->text('remarks')->nullable()->after('packing_instructions');
        });

        Schema::create('packaging_list_label_prints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('packaging_list_carton_id')->constrained()->cascadeOnDelete();
            $table->string('label_format', 30)->default('a4');
            $table->unsignedInteger('copies')->default(1);
            $table->string('reason')->nullable();
            $table->foreignId('printed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('printed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('packaging_list_label_prints');
        Schema::table('packaging_list_items', function (Blueprint $table) {
            $table->dropColumn(['source_type','source_item_id','source_quantity','previously_packed_quantity','sku','customer_item_code','hs_code','batch_number','serial_numbers','manufactured_at','expires_at','country_of_origin','unit_net_weight','unit_gross_weight','packing_instructions','remarks']);
        });
        Schema::table('packaging_list_cartons', function (Blueprint $table) {
            $table->dropUnique(['barcode']); $table->dropUnique(['public_token']);
            $table->dropColumn(['packing_mode','carton_number','carton_type','barcode','public_token','shipping_mark','material','pallet_number','seal_number','tare_weight','is_fragile','keep_dry','this_side_up','is_stackable','is_hazardous','handling_instructions']);
        });
        Schema::table('packaging_lists', function (Blueprint $table) {
            $table->dropForeign(['purchase_invoice_id']); $table->dropForeign(['packing_owner_id']);
            $table->dropColumn(['source_type','source_id','purchase_invoice_id','packing_owner_id','auto_generated','packing_warnings']);
        });
    }
};
