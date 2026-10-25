<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            $table->string('kyc_status', 30)->default('draft')->index();
            $table->string('kyc_public_token', 48)->nullable()->unique();
            $table->unsignedInteger('kyc_version')->default(0);
            $table->json('kyc_data')->nullable();
            $table->json('kyc_submitted_data')->nullable();
            $table->timestamp('kyc_sent_at')->nullable();
            $table->timestamp('kyc_submitted_at')->nullable();
            $table->timestamp('kyc_reviewed_at')->nullable();
            $table->foreignId('kyc_maker_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('kyc_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('kyc_revision_note')->nullable();
            $table->text('kyc_rejection_reason')->nullable();
        });
        Schema::create('vendor_kyc_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedInteger('file_size');
            $table->unsignedInteger('kyc_version');
            $table->timestamps();
        });
        Schema::create('vendor_kyc_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->string('status', 30);
            $table->unsignedInteger('kyc_version');
            $table->text('reason')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_kyc_events');
        Schema::dropIfExists('vendor_kyc_documents');
        Schema::table('vendors', function (Blueprint $table) {
            $table->dropIndex(['kyc_status']);
            $table->dropUnique(['kyc_public_token']);
            $table->dropForeign(['kyc_maker_id']);
            $table->dropForeign(['kyc_reviewed_by']);
            $table->dropColumn(['kyc_status', 'kyc_public_token', 'kyc_version', 'kyc_data', 'kyc_submitted_data',
                'kyc_sent_at', 'kyc_submitted_at', 'kyc_reviewed_at', 'kyc_maker_id', 'kyc_reviewed_by', 'kyc_revision_note', 'kyc_rejection_reason']);
        });
    }
};
