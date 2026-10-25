<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_products', function (Blueprint $table) {
            $table->decimal('production_completed_quantity', 14, 3)->default(0)->after('actual_ready_date');
            $table->text('production_evidence')->nullable()->after('production_completed_quantity');
            $table->foreignId('production_recorded_by')->nullable()->after('production_evidence')->constrained('users')->nullOnDelete();
            $table->timestamp('production_recorded_at')->nullable()->after('production_recorded_by');
        });

        Schema::table('shipment_items', function (Blueprint $table) {
            $table->foreignId('project_product_id')->nullable()->after('shipment_id')->constrained()->nullOnDelete();
        });

        Schema::create('project_quality_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_product_id')->constrained()->cascadeOnDelete();
            $table->string('batch_reference')->nullable();
            $table->decimal('inspected_quantity', 14, 3);
            $table->decimal('passed_quantity', 14, 3)->default(0);
            $table->decimal('rejected_quantity', 14, 3)->default(0);
            $table->decimal('rework_quantity', 14, 3)->default(0);
            $table->string('result', 30);
            $table->json('checklist');
            $table->text('notes')->nullable();
            $table->string('evidence_path');
            $table->string('evidence_original_name');
            $table->string('evidence_mime', 120)->nullable();
            $table->unsignedBigInteger('evidence_size')->default(0);
            $table->string('evidence_hash', 64);
            $table->string('content_hash', 64);
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('inspected_at');
            $table->timestamps();
            $table->index(['project_product_id', 'inspected_at'], 'project_qc_product_date_idx');
            $table->index(['project_id', 'result'], 'project_qc_project_result_idx');
        });

        Schema::table('packaging_list_items', function (Blueprint $table) {
            $table->foreignId('project_product_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->index(['project_product_id', 'total_quantity'], 'packaging_items_project_product_idx');
        });
    }

    public function down(): void
    {
        Schema::table('packaging_list_items', function (Blueprint $table) {
            $table->dropIndex('packaging_items_project_product_idx');
            $table->dropConstrainedForeignId('project_product_id');
        });
        Schema::dropIfExists('project_quality_inspections');
        Schema::table('shipment_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_product_id');
        });
        Schema::table('project_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_recorded_by');
            $table->dropColumn(['production_completed_quantity', 'production_evidence', 'production_recorded_at']);
        });
    }
};
